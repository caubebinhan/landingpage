---
seo_title: "2PLとOCC比較:ロック方式が並行制御性能を左右する理由"
seo_description: "two-phase locking(2PL)とoptimistic concurrency control(OCC)をMESIプロトコルやZipfian負荷の観点から比較し、実務でどちらを選ぶべきかを解説する。"
focus_keyword: "two-phase locking (2PL) vs optimistic concurrency control (OCC)"
---

# 13: Two-Phase Locking (2PL) vs. Optimistic Concurrency Control (OCC): A Micro-Architectural and Algorithmic Deep-Dive (マイクロアーキテクチャおよびアルゴリズムの徹底解説)

## エグゼクティブ・サマリー (What You Are Reading)

データベースの並行処理制御を支える二つの基本戦略が、**Two-Phase Locking (2PL)**と**Optimistic Concurrency Control (OCC)**だ。この記事ではその二つを、教科書的な定義だけでなく、シリコンの物理特性、OSのメモリアロケータ、CPUキャッシュコヒーレンシプロトコルとどう絡み合うかというレベルまで踏み込んで比較する。

**押さえておきたいポイント:**
- 悲観的にブロックする2PLと、楽観的にアボート・リトライで検証するOCC、両者の根本的なトレードオフ。
- 2PLがロック起因のキャッシュ無効化(False Sharing、MESIプロトコル)にどう苦しむか、OCCがthread-local storageやメモリバリアの限界をどう押し広げるか。
- 高負荷・偏りのあるアクセスパターン下で、OCCが「optimistic thrashing」に陥って崩壊する理由と、2PLがスリープキューへ緩やかに劣化する理由。
- どちらのプロトコルをいつ使うべきか、そして現代の分散データベースがMVCC + 2PLのようなハイブリッド構成に落ち着く理由。

## 根本的な問題 (The Core Problem)

マルチスレッドのデータベース環境で、並行実行がある逐次実行と等価であることを保証する**Serializability**の達成は、避けて通れない課題だ。

データベースアーキテクトは常にこのジレンマに直面する。
- 競合が頻繁に起きると想定するなら**Pessimistic Locking (2PL)**を使う。ただしロックはスレッドを待たせ、CPU利用率の低下、コンテキストスイッチのオーバーヘッド、複雑なデッドロック処理を招く。
- 競合が稀だと想定するなら**Optimistic Concurrency Control (OCC)**を使う。だが競合が実際に頻発する場合(例えばECサイトでバズった商品が一気に売れるようなケース)、スレッドは検証・失敗・再試行を延々と繰り返す。CPU利用率は100%に張り付くのに、実効スループットはゼロに近づくという最悪の正のフィードバックループ、いわゆるOptimistic Thrashingが発生する。

本質的な課題は、アトミック命令の使用を最小限に抑え、バスの飽和を避けながら、ワークロードの偏りが動的に変化しても壊滅的なスループット崩壊を起こさない同期メカニズムをどう設計するか、という点にある。

## 深い技術的分析 (Deep Technical Analysis)

### トランザクション並行処理制御の理論的基盤

Serializabilityの数学的基盤は、競合グラフ$G = (V, E)$にある。頂点$V$はコミット済みトランザクション、エッジ$E$は競合(read-write、write-read、write-write)を表す。スケジュールがconflict-serializableと言えるのは、$G$が厳密に非巡回である場合に限られる。

#### Two-Phase Locking (2PL)

2PLは、次の厳格なルールを課すことで非巡回性を保証する。
- **Growing Phase:** トランザクションはロックを取得できるが、解放はできない。
- **Shrinking Phase:** ロックを解放できるが、新規取得はできない。

カスケーディングアボート($T_j$が$T_i$の未コミットデータを読み、その後$T_i$がアボートする状況)を防ぐため、実運用ではコミットかアボートまで排他ロックを保持し続ける**Strict Two-Phase Locking (S2PL)**が使われる。2PLにはデッドロック検出(タージャンの強連結成分アルゴリズムを$\mathcal{O}(V+E)$で回す)や、Wait-Die/Wound-Waitのような防止スキームが必要になる。

#### Optimistic Concurrency Control (OCC)

OCCはトランザクションを三段階に分ける。
1. **Read Phase:** すべての操作をローカルコピー上で実行し、read set $RS(T_i)$とwrite set $WS(T_i)$を記録する。
2. **Validation Phase:** $T_i$の集合と、同時にコミットされた他のトランザクションの集合との重なりをチェックする。
3. **Write Phase:** 検証が通れば、ローカルの変更をグローバル状態へ反映する。

検証に失敗すればワークスペースは丸ごと破棄される。データ競合が激しくなるほど、検証失敗の確率$P_{abort}$は指数関数的に跳ね上がる。

### マイクロアーキテクチャへの影響とハードウェアレベルの同期

2PLとOCCの理論上の違いは、QPI/UPIインターコネクトを経由する**CPUキャッシュコヒーレンシプロトコル(MOESI)**のレベルまで下りると、はっきりと物理的な形で現れてくる。

#### ハードウェア視点で見た2PLのコスト

ロックマネージャーの実体は、ミューテックスやスピンロックを詰め込んだ巨大なハッシュテーブルだ。スレッドがロックを取得するたびにアトミック命令(x86_64なら`LOCK CMPXCHG`)を発行する。この手の命令はプロセッサのストアバッファを迂回し、完全なメモリバリアとして機能して命令のシリアル化を強制するため、コストが高い。

さらに、無関係な二つのロックがたまたま同じ64バイトのキャッシュラインに乗ってしまうと**False Sharing**が発生する。MESIプロトコルは、あるスレッドがロックを取得するたびに全コアにわたってそのキャッシュラインを無効化してしまい、メモリバスのトラフィックが跳ね上がる。まともなロックマネージャーは、NUMA環境で生き残るために`alignas(64)`で構造体を意図的にパディングしておく必要がある。

$$
T_{throughput\_2PL} = \frac{N_{cores}}{T_{exec} + N_{locks} \times \left(T_{atomic} + P_{contention} \times T_{wait}\right) + T_{deadlock\_detection}}
$$

#### ハードウェア視点で見たOCCのコスト

OCCはRead Phaseの間、アトミック操作をほぼ回避できる。トランザクションはThread-Local Storage(TLS)だけを変更するので、L1/L2キャッシュはコヒーレンシ無効化の被害を受けにくい。

ただし**Validation Phase**は、そのままアムダールのボトルネックになる。検証処理は、多くの場合グローバルなseqlockで守られたクリティカルセクションに入らざるを得ない。加えて、大量の一時ワークスペースの確保と解放が、`jemalloc`のようなOSのメモリアロケータに負荷をかける。割り当て頻度がカーネルの仮想メモリサブシステムを飽和させると、全コアでTLBシュートダウンが発生する。

$$
T_{throughput\_OCC} = \frac{N_{cores} \times (1 - P_{abort})}{T_{read\_phase} + T_{validation\_phase} + T_{write\_phase} + P_{abort} \times T_{retry\_penalty}}
$$

### アルゴリズムの漸近的挙動とZipfianワークロード

両プロトコルの性能を正しく評価するには、Zipf分布($\alpha > 0.9$)でモデル化されるような、極端に偏ったアクセスパターンに対する挙動を見る必要がある。

**OCC**では、偏りの強いワークロードが$P_{abort}$を急上昇させる。アボートされたトランザクションはすぐに再起動され、到着率$\lambda$を人為的に膨らませる。これが**Optimistic Thrashing Threshold**を生み出す。リトライ率がコミット率を上回った瞬間、システムは崩壊する。CPU使用率は100%に達しているのに、実効スループットはほぼゼロという状態だ。検証アルゴリズム自体、ブルームフィルターやロックフリーハッシュセットで最適化しない限り、集合演算に$\mathcal{O}(K \times R_{size} \times W_{size})$の時間がかかる。

```rust
// Simplified Rust OCC Validation Logic
pub fn validate_and_commit(&self, mut txn: Transaction) -> Result<(), &'static str> {
    let commit_timestamp = self.global_timestamp.fetch_add(1, Ordering::SeqCst);
    let history = self.committed_transactions.read().unwrap();
    
    // Critical Validation Phase: Check for overlapping read/write sets
    for past_txn in history.iter() {
        if past_txn.start_timestamp > txn.start_timestamp {
            // Validation fails if past transaction modified memory we read
            if !txn.read_set.is_disjoint(&past_txn.write_set) {
                return Err("Validation Failed: Read-Write Conflict");
            }
        }
    }
    // Proceed to Write Phase...
    Ok(())
}
```

一方**2PL**では、ロック待ちキューの長さ$L$が伸びていくにつれ、コストは$\mathcal{O}(L)$で増加する。ただし2PLは極端な偏りに対してもう少し上品に振る舞う。明示的なロック機構が一種の自己調整スロットルとして働き、OSのスケジューラがブロックされたスレッドをスリープさせてCPUを節約するため、スラッシングの正のフィードバックループが起きにくい。スループットは頭打ちにはなるが、崩壊まではしない。

```cpp
// Advanced C++ 2PL Lock Manager snippet handling wait queues
bool acquire_lock(uint64_t txn_id, uint64_t data_id, LockMode mode) {
    // Hash table lookup...
    std::unique_lock<std::mutex> lock(state->bucket_mutex);
    
    if (mode == LockMode::EXCLUSIVE && state->shared_count == 0 && !state->exclusive_held) {
        state->exclusive_held = true;
        return true;
    } else {
        // Conflict: Append to wait queue, OS suspends thread (conserving CPU)
        state->wait_queue.push_back({txn_id, mode, false});
        state->cv.wait(lock, [&]{ return check_grant_condition(state, txn_id, mode); });
        return true; 
    }
}
```

### Hardware Transactional Memory (HTM) がもたらす変化

Intel TSXのような先進的なプロセッサは、OCCの考え方をシリコンの論理ゲートレベルに落とし込もうとしている。**Hardware Transactional Memory (HTM)**は、L1キャッシュを投機的なバッファとして使い、キャッシュラインのメタデータビットを通じてread/write setを追跡する。メモリバス上で競合がスヌープされると、ハードウェアはクロックサイクル単位の速さでトランザクションをアボートさせる。HTMはソフトウェアによる検証のオーバーヘッドを取り除いてくれるが、L1キャッシュの容量という物理的な制約に縛られる。トランザクションのデータセットがL1を超えると容量アボートが発生し、結局は2PLベースのソフトウェアフォールバックに戻ることになる。

## 教訓とベストプラクティス (Lessons Learned & Best Practices)

1. **ワークロードの偏りを事前に把握しておく。** チケット予約や在庫の同時減算のような、競合が激しいシステムに素のOCCをそのまま投入してはいけない。Optimistic Thrashingは致命的なダウンタイムを引き起こす。OCCが本領を発揮するのは、読み取り中心の分析処理や、うまく分割されたマイクロサービスの領域だ。
2. **パディングは省略できない工程だと考える。** 2PLのロックマネージャーを実装するなら、ロックバケットに`alignas(64)`や`alignas(128)`を必ず指定する。これを怠るとFalse Sharingが発生し、64コアの強力なマシンがデュアルコアのノートPCより遅くなるという皮肉な結果を招く。
3. **検証フェーズがボトルネックになりうることを忘れない。** OCCは万能薬ではない。検証フェーズは何らかのクリティカルセクションを必要とする。高速な集合演算(ブルームフィルターなど)やエポックベースのメモリ回収で最適化しない限り、そこが最終的なボトルネックになる。
4. **実務ではハイブリッド構成が標準になっている。** 現代のエンジンで純粋な2PLや純粋なOCCだけを使うケースはほとんどない。MVCCと書き込み用のStrict 2PLを組み合わせたり、キューの深さに応じてプロトコルを動的に切り替える適応型の仕組みを採用したりするのが一般的だ。

## 結論

Two-Phase LockingとOptimistic Concurrency Controlの選択は、単なる学術的な議論ではない。データベースエンジンがシリコンの物理特性とどう向き合うかを決める、れっきとしたアーキテクチャ上の意思決定だ。2PLは高競合下での予測可能な挙動を得るためにCPUパイプラインの効率を犠牲にし、OCCは低競合下で最大限のロックフリースループットを得るためにメモリ管理のオーバーヘッドと検証の複雑さを引き受ける。MESIキャッシュコヒーレンシからOSのスレッドスケジューリングまで、この両方がマイクロアーキテクチャに及ぼす影響を理解していることこそ、優れたデータベースシステムアーキテクトを見分ける決定的な違いになる。
