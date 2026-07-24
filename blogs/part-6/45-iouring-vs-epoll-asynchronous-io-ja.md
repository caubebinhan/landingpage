---
seo_title: "io_uring 対 epoll：DBの非同期I/O徹底比較"
seo_description: "io_uringがepollやLinux AIOをなぜ上回るのか。リングバッファ、SQPOLL、固定バッファの仕組みからデータベースエンジンへの実装まで解説する。"
focus_keyword: "io_uring epoll 比較"
---

# `io_uring` 対 `epoll`：データベースアーキテクチャにおける非同期I/Oの新時代

## エグゼクティブサマリーと中核課題（Core Problem Statement）

`epoll`機構（kernel 2.5で導入）は、C10K問題——1万接続を同時にさばくという課題——を解決する手段として、20年以上にわたりLinuxサーバーの標準であり続けた。だが話が変わったのは、ストレージがミリ秒単位のHDDからマイクロ秒単位のPCIe NVMe Gen 4/5へ移行してからだ。`epoll`を軸にした従来の非同期I/Oモデルは、この速度差の前でマイクロアーキテクチャレベルの弱点をあらわにすることになった。

**中核課題**：ScyllaDB、Aerospike、PostgreSQLのようなエンジンが頭打ちになっているのは、ストレージの物理限界のせいではない。原因は**Linuxというオペレーティングシステムそのもののソフトウェアオーバーヘッド**にある。`epoll`や旧来のPOSIX AIOで非同期I/Oを行おうとすると、CPUはユーザー空間とカーネル空間の境界を何度も往復する羽目になる。`read()`、`write()`、`epoll_wait()`——どのシステムコールも、コンテキストスイッチ、KPTIによるページテーブル保護、データコピーのために数千クロックサイクルを食う。高速なNVMe環境では、CPUはデータを実際に運ぶ時間より、OSとの事務手続きに費やす時間の方が長くなってしまう。

Linux kernel 5.1で登場した**`io_uring`**は、このI/Oアーキテクチャを根本から作り直すものだ。ユーザー空間とカーネル空間が直接共有する2つのリングバッファをメモリマップすることで、アプリケーションは**システムコールを一切発行することなく**数百万件のI/O要求を送信し、その結果を受け取れるようになった。

本稿では`io_uring`のマイクロアーキテクチャを掘り下げ、`epoll`や`linux-aio`が抱える物理的な限界と対比しながら、データベースエンジンがこの技術に合わせてコアをどう再設計しているかを見ていく。`io_uring`と`epoll`のどちらを選ぶべきかという問いの実質は、まさにこの設計思想の違いにある。

---

## `epoll`の危機と非同期I/Oの起源

### `epoll`の本質（イベント駆動型のレディネス通知）

`epoll`はネットワークソケットのために生まれた仕組みで、その動作モデルは**完了通知**ではなく**準備完了通知**に依存している。

流れはこうなる。
1. `epoll_wait()`を呼び、スレッドをブロックして待つ。
2. ネットワークカードがパケットを受け取ると、カーネルが起こしてくれる。「ソケット5にデータが届いた、読みに行け」というわけだ。
3. アプリケーションは改めて`read(5)`を呼び出し、カーネルバッファからユーザーバッファへ実際にデータをコピーする。

総レイテンシ$T_{epoll}$は次のように表せる。
$$T_{epoll} = t_{syscall(epoll\_wait)} + t_{ctx\_switch} + t_{syscall(read)} + t_{vfs\_lookup} + t_{hardware\_io} + t_{interrupt}$$

### ディスクストレージI/Oにおける`epoll`の惨事

ネットワーク相手なら`epoll`は快調に動く。だがローカルディスク(ファイルシステムI/O)が相手だとまったく機能しない。Linuxでは、ext4やxfsといった通常のファイルは常に`epoll`に対して「準備完了」を返す。ディスク上のファイルに`epoll_wait()`を呼べば即座に戻ってくる。ところが実際に`read()`を呼び、データがページキャッシュに載っていなければ、**カーネルは何も言わずにアプリケーションのスレッドをブロックし**、ディスクからデータが届くのを待つ。

この「静かなブロッキング」は、イベントループという設計思想そのものを裏切る挙動だ。たった一つの`read()`が詰まっただけで、同じNodeJSやNGINXスレッド上にいる何千もの他クライアント接続まで一緒にフリーズしてしまう。

### Linux AIO（`io_submit`）の崩壊

非同期ディスクI/Oのために、Linuxはかつて`linux-aio`(`io_submit`と`io_getevents`)というインターフェースを用意していた。しかしLinus Torvalds自身がこの設計を公然と批判した通り、欠陥は少なくなかった。
1. **`O_DIRECT`しか使えない。** バッファリングを自前で管理し、ページキャッシュを完全に迂回する必要がある。バッファードI/Oでは動かない。
2. **メタデータ操作でやはりブロックする。** `O_DIRECT`を使っていても、ファイルシステムがブロック割り当てやiノードロックを必要とする場面では`io_submit`がブロックされうる。
3. **システムコールのコストは消えない。** バッチで要求をまとめても、少なくとも1回の`io_submit`呼び出しは避けられない。

これらの欠点を避けるため、PostgreSQL、MySQL、MongoDBといったデータベースは巨大な**スレッドプール**を作らざるを得なかった。ブロッキングする`read`/`write`呼び出しをバックグラウンドスレッドに投げ込み、メインスレッドを守る戦略だ。だが何百ものI/Oスレッドの間で絶え間なく起きるコンテキストスイッチは、CPUのL1/L2キャッシュを荒らし続ける。

---

## `io_uring`のマイクロアーキテクチャ：共有メモリの仕組み

`io_uring`は、Linuxカーネルのブロックレイヤーを長年メンテナンスしてきたJens Axboeによって、これらの弱点を取り除くために設計された。発想は「準備完了」モデルから**「完了」**モデルへと切り替わる。カーネルに作業内容を渡せば、あとはカーネルが最初から最後まで処理し、終わったらメモリに結果を書き戻してくれる。

### ロックフリーのリングバッファ構造

`io_uring`は一方向の循環リングバッファを2つ用意する。
1. **サブミッションキュー(SQ)** — タスクをユーザーからカーネルへ送るキューで、SQE(Submission Queue Entry)スロットの集まりだ。各SQEは「ファイルXから4KB読んでバッファYへ入れる」といったコマンドを記述する。
2. **コンプリーションキュー(CQ)** — 結果をカーネルからユーザーへ返すキューで、CQEスロットの集まり。各CQEは「先ほどのコマンドが完了し、エラーコード0(成功)を返した」ことを伝える。

この2つの配列は、アプリケーションプロセスの仮想メモリへ直接メモリマップされる。つまりアプリケーションとカーネルは文字通り同じRAM領域を見ている。

### メモリバリアによる同期

ここにはミューテックスもスピンロックも一切登場しない。同期はすべて、アトミックな`Head`/`Tail`ポインタとハードウェアレベルのメモリバリアだけで成り立っている。

```mermaid
graph TD
    subgraph ユーザー空間アーキテクチャ
        DB[データベースエンジンスレッド]
        SQ_Tail[SQ Tailポインタ（アトミック）]
        CQ_Head[CQ Headポインタ（アトミック）]
        SQ_Ring[サブミッションキュー配列 - SQEs mmap]
        CQ_Ring[コンプリーションキュー配列 - CQEs mmap]
    end
    
    subgraph カーネル空間アーキテクチャ
        SQ_Head[SQ Headポインタ]
        CQ_Tail[CQ Tailポインタ]
        Kernel_Worker[io_wq 非同期ワーカー]
        Block_Layer[Linuxブロックレイヤー & NVMeドライバ]
    end

    DB -->|1. I/O設定を書き込む（オペコード、バッファ）| SQ_Ring
    DB -->|2. smp_store_release()でアトミック更新| SQ_Tail
    SQ_Tail -.->|メモリバリア| SQ_Head
    Kernel_Worker -->|3. I/Oコマンドを読み取る| SQ_Ring
    Kernel_Worker -->|4. コマンドをディスクへ振り分け| Block_Layer
    Block_Layer -->|5. 完了シグナル（DMA完了）| Kernel_Worker
    Kernel_Worker -->|6. 結果コードを書き込む（Status 0）| CQ_Ring
    Kernel_Worker -->|7. smp_store_release()でアトミック更新| CQ_Tail
    CQ_Tail -.->|メモリバリア| CQ_Head
    DB -->|8. CQEを読む（smp_load_acquire）システムコールなし| CQ_Ring
```

データベースエンジンが100件の書き込みタスクをディスクへ送りたいとする。やることは単純で、RAM上の100個のSQEスロットにデータを書き、`SQ_Tail`ポインタを進めるだけだ。あとはカーネルを起こして処理させるために、`io_uring_enter()`を1回だけ呼ぶ。かつて100回必要だった`write()`システムコールが、これで1回に減る。

### 究極の境地：SQPOLLによるシステムコールの完全排除

超低レイテンシを突き詰めたい場合、`io_uring`は`IORING_SETUP_SQPOLL`という設定フラグを用意している。これを有効にすると、カーネルは特定のCPUコアに固定された専用スレッドを一つ立ち上げ、アプリケーションの`SQ_Tail`ポインタをひたすらポーリングし続ける。

アプリケーションがSQに要求を積むと、そのカーネルスレッドは共有メモリ越しに即座にそれを検知し、自動的に処理を始める。**この瞬間、システムコールの数はちょうどゼロになる。**アプリケーションはI/Oを送信し結果を受け取るが、コンテキストスイッチは一度も発生しない。このときのI/Oレイテンシ($T_{iouring\_sqpoll}$)は、ほぼPCIeバス上を信号が伝わる時間だけで決まる。
$$T_{iouring\_sqpoll} = t_{mem\_barrier} + t_{pcie\_dma\_transfer} + t_{nvme\_flash\_prog}$$

---

## データベースエンジンのための高度な武器

`io_uring`が解決するのは基本的なI/Oだけではない。データベースエンジンにはさらに一段踏み込んだ武器一式が用意されている。

### 固定バッファの登録（Fixed Buffers）

通常、`read()`や`write()`を呼ぶたびに、カーネルは`iovec`構造体を組み立て、ハードウェアが直接DMAできるようユーザーのRAMページをIOMMUにマッピングし、終わったらそれを外す、という手順を踏む。これは毎回かなりのコストがかかる。

`io_uring`を使えば、データベースは大きなRAMブロックを事前に一度だけ登録できる。カーネルはその領域を物理的にピン留めし、IOMMUのマッピングもあらかじめ設定しておく。以降の`IORING_OP_WRITE_FIXED`操作は、アドレス変換の手順を挟むことなく、ディスクコントローラからそのRAM領域へ直接データが流れ込む。

### 連結されたリクエスト（Linked SQEs）

データベースにとってI/Oの順序は死活問題だ。たとえば、まずデータをディスクへ書き込み(コマンド1)、それがフラッシュされたことを保証し(コマンド2 — `fsync`)、それからようやくメタデータを更新する(コマンド3)、という手順を守らなければならない場面がある。

Linux AIOでは、これを一つずつ待つしかなかった。コマンド1の完了を待ち、コマンド2を送信し、その完了を待ち、コマンド3を送信する、という具合だ。`io_uring`では`IOSQE_IO_LINK`フラグを使い、3つのコマンドを一度にまとめてキューへ送れる。カーネルは、コマンド1が成功した場合に限りコマンド2を実行することを保証してくれる。ユーザーとカーネルの間のやり取りが大きく減るわけだ。

### ネットワークとストレージの統合

`io_uring`の最大の強みは、ネットワーク、ファイル、タイムアウト、`fsync`、`fallocate`といったあらゆる種類の操作を同じ枠組みで扱える点にある。データベースはもう、ネットワークソケット用の`epoll`とディスクI/O用のスレッドプールという二つのアーキテクチャを別々に維持する必要がない。

アーキテクトは1つのCPUコアに対して単一のイベントループを組むだけでいい。そのコア一つでTCP接続を受け入れ(`IORING_OP_ACCEPT`)、HTTPリクエストを読み取り(`IORING_OP_RECV`)、データをディスクへ書き込む(`IORING_OP_WRITEV`)——すべてが一つの`io_uring`リングに集約される。

---

## C++によるノンブロッキング実装

以下は、`liburing`を使った現代的なストレージエンジンの簡略化した疑似コードだ。アプリケーションがC++のコンテキストオブジェクトを`user_data`に添付し、結果が返ってきた際にコマンドと照合している点に注目してほしい。

```cpp
#include <liburing.h>
#include <memory>
#include <cstdint>
#include <iostream>
#include <stdexcept>

// アプリケーション内部のコンテキストを運ぶカスタムリクエスト構造体
struct IOTransactionContext {
    int file_descriptor;
    uint64_t disk_offset;
    std::unique_ptr<char[]> memory_buffer;
    size_t length;
    uint32_t transaction_id;
};

class UltraFastStorageEngine {
private:
    struct io_uring ring;
    const unsigned int RING_DEPTH = 4096;

public:
    UltraFastStorageEngine() {
        struct io_uring_params params = {};
        // 最大限の最適化：SQPOLLを使ってシステムコールを完全に排除する
        params.flags |= IORING_SETUP_SQPOLL;
        params.sq_thread_idle = 2000; // 仕事がない場合、2ms後にカーネルスレッドをスリープさせる
        
        if (io_uring_queue_init_params(RING_DEPTH, &ring, &params) < 0) {
            throw std::runtime_error("カーネルが非対応、またはulimitによる制限があります！");
        }
    }

    void submit_async_write(IOTransactionContext* ctx) {
        // 共有リングバッファから空のSQEスロットを取得
        struct io_uring_sqe *sqe = io_uring_get_sqe(&ring);
        if (!sqe) {
            // SQが満杯なので、カーネルに消費させるため能動的に送信する
            io_uring_submit(&ring);
            sqe = io_uring_get_sqe(&ring);
        }
        
        // 低レベルの非同期書き込みオペコードを設定
        io_uring_prep_write(sqe, ctx->file_descriptor, 
                            ctx->memory_buffer.get(), ctx->length, ctx->disk_offset);
                           
        // 重要：CQE受信時にコンテキストを復元できるよう、SQEのメタデータ（64ビット整数）にC++オブジェクトのポインタを添付する
        io_uring_sqe_set_data(sqe, ctx);
    }

    void reap_completions_lockfree() {
        struct io_uring_cqe *cqe;
        unsigned head;
        unsigned count = 0;

        // ローカルRAM上だけでCQリングバッファを走査する（システムコールなし）
        io_uring_for_each_cqe(&ring, head, cqe) {
            // user_dataポインタをキャストし直し、コンテキストを復元する
            IOTransactionContext* ctx = static_cast<IOTransactionContext*>(io_uring_cqe_get_data(cqe));
            
            if (cqe->res < 0) {
                std::cerr << "トランザクション " << ctx->transaction_id 
                          << " でI/Oエラー（エラーコード：" << cqe->res << "）\n";
            } else {
                // 成功時のビジネスロジックを処理する
                finalize_transaction(ctx, cqe->res);
            }
            count++;
        }
        
        if (count > 0) {
            // アトミックなCQ Headを更新し、収穫が完了したことをカーネルへ伝える
            io_uring_cq_advance(&ring, count);
        }
    }

private:
    void finalize_transaction(IOTransactionContext* ctx, int bytes_written) {
        // ネットワーク経由でクライアントへACKを送るか、WALへ記録する
        delete ctx; // メモリを片付ける
    }
    
    ~UltraFastStorageEngine() {
        io_uring_queue_exit(&ring);
    }
};
```

---

## システムアーキテクトへの教訓とベストプラクティス

`epoll`から`io_uring`への世代交代はすでに進行中だ(Redis 7.0、PostgreSQL 15、NodeJS 20+も採用を試し始めている)。ただし、この技術を使いこなすには押さえておくべき勘所がいくつかある。

1. **OSキャッシュの限界を理解する。** `io_uring`で読み込むファイルのデータがすでにOSページキャッシュに載っている場合、カーネルのバックグラウンドワーカー`io_wq`が結局介入することになり、その分速度は落ちる。`io_uring`が真価を発揮するのは**`O_DIRECT`**と組み合わせたときで、そのためにはデータベース自身がバッファプールを持ち、OSのキャッシュを迂回する覚悟が要る。
2. **セキュリティ上の露出に注意する。** カーネルの構造体をmmapで直接ユーザー空間に晒すという性質上、権限昇格につながるCVEが過去に何度も報告されている。Docker、Kubernetes、SELinuxはデフォルトでseccompフィルタを通じて`io_uring`を無効化していることが多く、明示的にホワイトリスト登録しない限りデータベースは起動すらできない。
3. **メモリのライフサイクルを慎重に管理する。** SQEに渡したバッファは、対応するCQEが返ってくるまで有効でなければならない。うっかりしたスレッドが書き込み完了前にそのバッファを解放してしまうと、カーネルはすでに別用途で使われているRAM領域へDMAでデータを書き込んでしまう。これはメモリ破損であり、プロセス全体を巻き込みかねないバグになる。
4. **超高速NVMe向けにはポーリングI/O(IOPOLL)も検討する。** レイテンシが10マイクロ秒を切るようなドライブでは、完了を知らせる割り込み自体がボトルネックになる——コンテキストスイッチだけで3〜4マイクロ秒かかるからだ。`IORING_SETUP_IOPOLL`を有効にすれば、カーネルはスリープする代わりにSSDの状態をビジーウェイトでポーリングする。CPUコアを1つ丸ごと使い切ることになるが、レイテンシは物理的な下限に近づく。
5. **Webサーバー向けに`epoll`を性急に手放さない。** `io_uring`はネットワークソケットも扱えるが、典型的な短命なHTTP/TCP接続を前提としたWebサーバーでは、`epoll`に対する優位性はアーキテクチャ全体を書き直すほどの規模ではないというのがベンチマークの実情だ。ストレージ領域では`io_uring`が明確に優れているが、ネットワーク領域では`epoll`は今も現役の選択肢である。

---
