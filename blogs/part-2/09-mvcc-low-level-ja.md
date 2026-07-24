---
seo_title: "MVCC低レベルアーキテクチャ:マルチバージョン同時実行制御の内部"
seo_description: "MVCC(Multi-Version Concurrency Control)の低レベルアーキテクチャを解説。ポインタ追跡、false sharing、Epoch-Based Reclamation、NUMA最適化まで踏み込んで分析する。"
focus_keyword: "MVCC 低レベルアーキテクチャ"
---

# Multi-Version Concurrency Control (MVCC): 低レベルアーキテクチャとマルチスレッドメモリ管理

## エグゼクティブサマリー

MVCC(Multi-Version Concurrency Control)は、現代のリレーショナルデータベースやマルチスレッドのトランザクショナルメモリシステムを支える基盤技術のひとつだ。ここでは、MVCCが最下層でどう動いているか、L1/L2/L3キャッシュやメインメモリ帯域幅、キャッシュコヒーレンスプロトコルといったハードウェアとどう噛み合っているかを、実装レベルまで踏み込んで見ていく。

読み進めると次のことが分かるはずだ。

- Append-Only、Time-Travel、Delta Storageという3つのマルチバージョンストレージモデルの違い
- ポインタ追跡(pointer chasing)やフォールスシェアリング(false sharing)がなぜ厄介なのか
- ロックフリーなガベージコレクションの手法、特にEpoch-Based Reclamation(EBR)の仕組み
- NUMAアーキテクチャ上でメモリ配置を最適化する際に押さえておくべき点

## MVCCが解決しようとした問題

大量のトランザクションを捌くOLTPシステムでは、一貫性を保ちながらスループットを稼ぐことが常に課題になる。従来のロックベースの仕組みは一貫性こそ守れるものの、読み取りトランザクションと書き込みトランザクションが互いに待ち合う構造を生み、マルチコア環境では深刻なボトルネックになりやすい。

MVCCはデータの複数バージョンを保持することで「読み取りが書き込みをブロックせず、書き込みも読み取りをブロックしない」状態を実現する。ただしこれは、マイクロアーキテクチャのレベルで新たな課題を持ち込むことにもなる。

1. **メモリ断片化とアクセスレイテンシ** — 複数バージョンの保持はメモリ割り当てに大きな負荷をかける。バージョンチェーンが長くなるほどCPUはポインタ追跡を強いられ、キャッシュミス率が跳ね上がる。
2. **マルチスレッド同期のコスト** — 共有データ構造への同時読み書きにはメモリバリアやアトミック操作が必要になり、メモリバスの混雑を招く。
3. **データライフサイクル管理** — 実行中の読み取りトランザクションを邪魔せずに古いバージョンを回収するタイミングの見極めは容易ではなく、扱いを誤るとメモリ枯渇(OOM)に直結する。

## 詳細な技術分析

### マルチバージョンストレージのアーキテクチャ

MVCCの数学的なモデルを考えると、時刻$t$におけるデータベースの論理状態はタプルの集合$D_t = \{R_1, R_2, \dots, R_n\}$として表せる。MVCCアーキテクチャは、各論理タプル$R_i$を物理バージョンの集合$V_i = \{v_{i,1}, v_{i,2}, \dots, v_{i,m}\}$へマッピングする。

各バージョン$v_{i,j}$には、2つのタイムスタンプ$[T_{begin}, T_{end})$で区切られた有効期間が紐づく。Read CommittedやSerializableといった分離レベルに応じて、トランザクション処理エンジンは読み取り操作にどのタイムスタンプを割り当てるかを決め、回復可能性と直列化可能性を確保する。

バージョンタプルの構造には、可視性判定のためのメタデータがヘッダーに埋め込まれているのが普通だ。具体的には、バージョンを作成したトランザクションID($TxnID_{creator}$)、削除したトランザクションID($TxnID_{deleter}$)、そしてバージョンチェーン内で前後のバージョンへ辿るポインタなどが含まれる。このヘッダーはだいたい16〜32バイト程度になる。

#### ストレージモデルの分類

物理バージョンの保存方式は、大きく3つに分かれる。

- **Append-Only Storage:** タプルの新しいバージョンをすべて、古いバージョンと同じテーブルスペースに追記していく方式。PostgreSQLが採用している。
- **Time-Travel Storage:** テーブルスペースにはメインバージョンだけを置き、古いバージョンの完全なコピーは別のストレージ領域へ移す方式。
- **Delta Storage:** 完全なコピーではなく差分だけを保存する方式。MySQLやOracleで使われている。

### ハードウェアの壁:ポインタ追跡とキャッシュミス

Append-Onlyモデルでは、更新率が$\lambda$更新/秒だとすると、メモリ膨張の速度は$\frac{dM}{dt} = \lambda \times S_{tuple}$になる。バージョンが断片化して連なっていくこのモデルは、CPUキャッシュの構造にとって相性が悪い。

バージョンチェーンを辿るとき、CPUはポインタ追跡という厄介な現象にぶつかる。ポインタを逆参照する際にL3キャッシュミスが起きる確率を$P_{miss}$、メインメモリアクセス時間を$T_{mem}$(だいたい100ns)、L1キャッシュアクセス時間を$T_{cache}$(約1ns)とし、辿る必要のあるバージョンチェーンの長さを$L$とすると、有効なバージョンを特定するまでの期待時間は次のようにモデル化できる。

$$E[T_{resolve}] = L \times \left( P_{miss} \times T_{mem} + (1 - P_{miss}) \times T_{cache} \right)$$

Append-Only構造ではメモリが断片化しやすいため、$P_{miss}$は境界値の$1.0$にかなり近づいてしまうことが多く、テーブルスキャンの性能や応答レイテンシを大きく損なう。

### Delta Storageによる最適化とFalse Sharingという課題

この物理的な壁を越えるため、Delta Storageモデルは差分の考え方をベースに設計し直されたものだ。メインバージョンをその場で(in-place)更新し、変更されたフィールドだけを含む差分レコードを生成する。この差分レコードはUndo Logと呼ばれるリングバッファ領域に積まれていく。メモリ消費速度は $\frac{dM_{undo}}{dt} = \lambda \times (S_{\Delta} + S_{metadata})$ という式で表せる。

ただし、古いバージョンを再構築するには、差分レコードを逆順に適用していくアルゴリズムが必要になる。さらにマルチスレッド環境でのメモリ安全性を保つため、`std::memory_order_acquire`のようなメモリバリア命令も欠かせない。

実装レベルで常につきまとうのがフォールスシェアリングだ。MESI(Modified、Exclusive、Shared、Invalid)というキャッシュ一貫性プロトコルの下では、あるCPUコア(Core A)が変数へ書き込むと、他のすべてのコア上にあるその変数を含むキャッシュライン全体が無効(Invalid)としてマークされてしまう。これを避けるには、C/C++の`alignas(64)`のようなメモリ配置指定を使い、データ構造が別々のキャッシュラインに収まるようコンパイラに強制させる必要がある。

```mermaid
graph TD
    subgraph CPU_Cache_Architecture
        L1_Core1[L1 Cache Core 0] -->|MESI Invalidate| L1_Core2[L1 Cache Core 1]
        L1_Core1 --> L2_Core1[L2 Cache]
        L1_Core2 --> L2_Core2[L2 Cache]
        L2_Core1 --> L3_Shared[L3 Shared Cache]
        L2_Core2 --> L3_Shared
    end
    subgraph Physical_Memory_Layout
        L3_Shared --> Main_Tuple[Main Version Tuple\nHeader | ID | Payload\nalignas 64 bytes]
        Main_Tuple -.->|Atomic Undo Pointer| Delta_1[Delta Record 1\nTxnID | Changed Columns]
        Delta_1 -.->|Atomic Undo Pointer| Delta_2[Delta Record 2\nTxnID | Changed Columns]
    end
    style Main_Tuple fill:#f9f,stroke:#333,stroke-width:2px
    style Delta_1 fill:#bbf,stroke:#333,stroke-width:1px
    style Delta_2 fill:#bbf,stroke:#333,stroke-width:1px
```

```cpp
// マルチスレッド環境におけるDelta StorageのLow-Levelデータ構造の図解
#include <atomic>
#include <cstdint>
#include <cstring>

// キャッシュライン上のFalse Sharingを防ぐための64バイトアライメント
struct alignas(64) UndoRecord {
    std::atomic<UndoRecord*> next_delta;
    uint64_t transaction_id;
    uint32_t delta_size;
    uint8_t payload[]; // フレキシブル配列メンバ
};

struct alignas(64) TupleHeader {
    uint64_t xmin; 
    std::atomic<uint64_t> xmax; 
    std::atomic<UndoRecord*> undo_pointer; 
    uint32_t tuple_length;
    uint16_t attributes_mask;
};

// Wait-Freeメカニズムを使用してDeltaを読み取り、適用する関数
void reconstruct_version(const TupleHeader* base_tuple, uint64_t read_ts, uint8_t* output_buffer) {
    std::memcpy(output_buffer, reinterpret_cast<const uint8_t*>(base_tuple) + sizeof(TupleHeader), base_tuple->tuple_length);
    UndoRecord* current_delta = base_tuple->undo_pointer.load(std::memory_order_acquire);
    
    while (current_delta != nullptr) {
        if (current_delta->transaction_id < read_ts) break; 
        apply_binary_patch_logic(output_buffer, current_delta->payload, current_delta->delta_size);
        current_delta = current_delta->next_delta.load(std::memory_order_acquire);
    }
}
```

### Epoch-Based Reclamation (EBR) によるガベージコレクション

古いバージョンが際限なく積み上がっていくとRAMを圧迫する。データベースの「事象の地平線」$TS_{min} = \min_{T \in ActiveTxns} (TS_{read}(T))$より前のバージョンはガベージとみなせる: $\forall v_k \in Memory, \text{IsGarbage}(v_k) \iff v_k.T_{end} < TS_{min}$。

コストの高い参照カウントを使う代わりに、HyPerやSiloのようなインメモリDBはEpoch-Based Reclamation(EBR)という手法を使う。時間軸をいくつかのエポック区間($E_1, E_2, \dots$)に区切り、更新スレッドは現在のエポックに対応するローカルなガベージリスト$GarbageList[E_{global}]$に古いメモリ領域へのポインタを積んでいく。実際のメモリ解放(`free()`)は、すべてのアクティブスレッドがそれより少なくとも2世代新しいエポックへ進んだ場合にのみ実行される。

$$\forall thread \in ActiveThreads, E_{local}(thread) > E_{safe} + 1$$

EBRの弱点は、1つのスレッドがスタックしただけで全体のガベージコレクションが止まってしまう点だ。ガベージが積み上がり続け、最終的にOOMを引き起こしかねない。

### NUMAアーキテクチャとTLBシュートダウン

OSのメモリ管理もMVCCに深く関わってくる。メモリを解放するとき(`munmap`)、カーネルはIPI(Inter-Processor Interrupt)を使ってTLBシュートダウンを引き起こし、CPUパイプラインフラッシュを誘発して大きなレイテンシを生む。

これを避けるため、実装ではローカルアリーナを持つユーザースペースのメモリアロケータ(`jemalloc`、`tcmalloc`など)を使うのが一般的だ。加えてNUMA(Non-Uniform Memory Access)アーキテクチャでは、RAMは各CPUソケットに紐づいているため、別のNUMAノードをまたぐアクセスは大きなレイテンシを招く。トランザクションのロールバックセグメントは、`numa_alloc_onnode`のような専用APIを使い、実行スレッドと同じNUMAノード上に確実に配置する必要がある。

## 教訓

MVCCの低レベルアーキテクチャを追ってきた中で、システムエンジニアにとって役立つ教訓はいくつかある。

1. **ハードウェアの挙動を理解すること** — CPUキャッシュ(L1/L2/L3)、メモリ帯域幅、NUMA接続の性質を無視して高性能な並行処理システムを設計することはできない。最適化されたコードは常に64バイトのキャッシュラインを意識する必要がある。
2. **False Sharingは徹底して避けること** — スレッド間でキャッシュラインを無駄に無効化し合わないよう、`alignas(64)`などでデータ構造のメモリ配置を丁寧に設計する。
3. **ガベージコレクションを遅延させること** — ホットパスでのロックやアトミック参照カウントは避け、Epoch-Based ReclamationやHazard Pointersを使ってメモリ解放をメインの実行経路から切り離す。
4. **カスタムアロケータを書くこと** — OS標準の`malloc`/`free`に頼ると、システムコールやTLBシュートダウン、断片化のせいで性能が落ちる。主要なシステムはどれもユーザースペースで独自のメモリプーリングを行っている。

## 結論

MVCCの設計は、単に時間軸上のバージョンを論理的に管理するだけの話ではない。突き詰めれば、ノイマン型アーキテクチャの物理的な制約とどう折り合いをつけるかという戦いだ。大規模な並行処理データベースの性能は、ポインタ逆参照のレイテンシをどう抑えるか、NUMAのパーティション構造をどう尊重するか、そしてロックフリーなガベージコレクションの仕組みをどう設計するかによって決まる。こうした低レベルの概念を理解しておくことは、システムソフトウェアエンジニアとして一段深いレベルに到達するための足がかりになるはずだ。
