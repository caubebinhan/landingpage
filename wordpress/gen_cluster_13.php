<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'lock_granularity.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
copy($image_path, $destination);
$filetype = wp_check_filetype($filename, null);
$attachment = [
    'post_mime_type' => $filetype['type'],
    'post_title'     => 'Lock Granularity',
    'post_content'   => '',
    'post_status'    => 'inherit'
];
$attach_id = wp_insert_attachment($attachment, $destination);
$attach_data = wp_generate_attachment_metadata($attach_id, $destination);
wp_update_attachment_metadata($attach_id, $attach_data);

// 2. Setup Categories and Tags
function setup_term($name, $taxonomy, $lang) {
    $term = get_term_by('name', $name, $taxonomy);
    if (!$term) {
        $term_info = wp_insert_term($name, $taxonomy);
        $term_id = $term_info['term_id'];
    } else {
        $term_id = $term->term_id;
    }
    pll_set_term_language($term_id, $lang);
    return $term_id;
}

$cat_en = setup_term('Transactions & Concurrency', 'category', 'en');
$cat_vi = setup_term('Giao Dịch & Đồng Thời', 'category', 'vi');
$cat_ja = setup_term('トランザクションと並行処理', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>The Chaos of Concurrency</h2>

<p>Imagine a bank with only one teller. If two customers want to deposit money, they must stand in a single-file line. It is slow, but perfectly safe. No money will be lost. Now imagine a bank with 1,000 tellers all grabbing money from the same vault at exactly the same millisecond. Without strict rules, the vault balance will become corrupted instantly. This is the fundamental problem of <strong>Concurrency</strong> in databases.</p>

<p>To prevent data corruption, databases use <strong>Locks</strong>. When Transaction A wants to update a user\'s balance, it places an "Exclusive Lock" on that record. If Transaction B also wants to update it, it must wait until A is finished. It sounds simple, but in 1975, Jim Gray published a Turing Award-winning paper titled <em>"Granularity of Locks and Degrees of Consistency in a Shared Database"</em>, revealing that locking is actually a terrifying mathematical balancing act.</p>

<h2>The Granularity Dilemma: Table vs. Row</h2>

<p>Gray’s paper formalized the concept of <strong>Lock Granularity</strong> (the size of the data being locked). Engineers are faced with a brutal trade-off:</p>

<ul>
<li><strong>Table-Level Locks (Coarse Granularity):</strong> If you place a lock on the entire <code>users</code> table, it requires only 1 lock in RAM. The memory overhead is virtually zero. But the concurrency is destroyed. If you are updating User 1, no one else can update User 2, User 3, or User 1,000,000. The database becomes a single-file line.</li>
<li><strong>Row-Level Locks (Fine Granularity):</strong> If you place a lock strictly on the exact row you are modifying, millions of users can update their own records simultaneously. Concurrency is maximized! But the memory overhead is catastrophic. If a transaction updates 1 million rows, the database must store and manage 1 million individual locks in RAM. The CPU will spend all its time managing locks instead of writing data.</li>
</ul>

<p>How do modern databases solve this? They use <strong>Lock Escalation</strong>. A transaction starts by acquiring thousands of tiny Row-Level locks. But if it crosses a certain threshold (e.g., trying to lock 20% of the table), the database panics, throws away the individual row locks, and aggressively upgrades to a single Table-Level lock. Suddenly, a fast query can "escalate" and unexpectedly freeze the entire application for everyone else.</p>

<h2>The Genius of Intention Locks</h2>

<p>If you have Row-Level locks, how do you drop a table? If an administrator runs <code>DROP TABLE users</code>, the database must ensure that absolutely no one is currently holding a row lock inside that table. Does the database have to scan all 10 million rows to check for locks before dropping the table? That would take forever.</p>

<p>Gray invented a brilliant solution called <strong>Intention Locks</strong>. When a transaction acquires an Exclusive Lock on a specific <em>row</em>, it must first place an "Intention Exclusive (IX) Lock" on the <em>table</em> itself.</p>

<p>The IX lock acts as a signpost at the front door of the building, saying: <em>"Someone is inside one of the rooms doing construction."</em> Now, when the administrator tries to drop the table, they request an Exclusive Lock on the building. The database simply looks at the front door, sees the IX signpost, and immediately says "No," without ever having to check the individual rooms.</p>

<h2>Deadlocks: The Unsolvable Standoff</h2>

<p>Gray also formalized the concept of the <strong>Deadlock</strong>. Transaction A locks Row 1 and waits for Row 2. Transaction B locks Row 2 and waits for Row 1. They will wait for eternity. There is no mathematical way to prevent this without destroying concurrency.</p>

<p>Instead of preventing it, Gray proposed <strong>Deadlock Detection</strong>. The database constantly runs a background algorithm that builds a "Wait-For Graph" (a map of who is waiting for whom). If it detects a cycle in the graph, it plays the role of a ruthless executioner. It randomly selects one transaction as the "victim," violently aborts it, rolls back its changes, and lets the other transaction proceed. As application developers, we simply have to accept that our transactions will sometimes be murdered by the database, and we must write code to catch the error and retry.</p>

<h2>Lessons Learned</h2>

<p>Concurrency is not a problem to be solved; it is a trade-off to be managed. Jim Gray’s paper provided the mathematical vocabulary (Row locks, Table locks, Intention locks, Deadlocks) that we still use 50 years later. Understanding these mechanics is what separates a junior developer who writes slow, deadlocking SQL, from a senior architect who designs systems that can handle a million transactions a second.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'The Chaos of Concurrency: Deadlocks and Lock Granularity',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Locking', 'Jim Gray', 'Concurrency', 'Deadlock']
]);
pll_set_post_language($post_en, 'en');
set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>Sự Hỗn Loạn Của Đồng Thời (Concurrency) Trong Hệ Thống Quy Mô Lớn</h2>

<p>Là một Engineering Manager, tôi thường thấy các lập trình viên mới ra trường viết code rất tự tin trên máy tính cá nhân (Localhost). Khi chỉ có một mình họ chạy ứng dụng, mọi câu lệnh SQL đều hoạt động hoàn hảo. Nhưng khi đẩy đoạn code đó lên Production—nơi có 10.000 user cùng thực hiện giao dịch trong đúng một tích tắc (millisecond)—hệ thống bắt đầu sụp đổ một cách bí ẩn: Dữ liệu bị ghi đè, số dư tài khoản bị âm, và các request bị treo cứng (Timeout).</p>

<p>Đây là bài toán muôn thuở của ngành Khoa học Máy tính: <strong>Concurrency (Xử lý đồng thời)</strong>. Hãy tưởng tượng một kho tiền chỉ có đúng một cửa ra vào. Nếu bắt mọi người xếp hàng một (Single-threaded), kho tiền cực kỳ an toàn, nhưng tốc độ phục vụ bằng 0. Nếu cho 10.000 người ùa vào cùng một lúc để bốc tiền, kho tiền sẽ bốc hơi trong chớp mắt vì dữ liệu bị sai lệch (Data Corruption).</p>

<p>Để ngăn chặn điều này, các hệ quản trị Cơ sở dữ liệu sinh ra một thứ gọi là <strong>Khóa (Locks)</strong>. Nếu Transaction A đang nạp tiền cho User 1, nó sẽ "Khóa" dòng dữ liệu đó lại. Transaction B muốn trừ tiền của User 1 thì phải đứng xếp hàng chờ A thả khóa ra. Nghe thì có vẻ đơn giản, nhưng vào năm 1975, nhà khoa học máy tính vĩ đại Jim Gray (người sau này đoạt giải Turing) đã xuất bản bài luận văn kinh điển: <em>"Granularity of Locks and Degrees of Consistency in a Shared Database"</em>. Bài báo chỉ ra rằng: Quản lý Khóa thực chất là một trò chơi đánh đổi vô cùng tàn khốc về mặt Toán học và Hiệu năng.</p>

<h2>Thảm Họa Đánh Đổi: Khóa Bảng (Table Lock) vs Khóa Hàng (Row Lock)</h2>

<p>Jim Gray đã chính thức hóa khái niệm <strong>Độ hạt của Khóa (Lock Granularity)</strong> - tức là kích thước của cục dữ liệu mà bạn muốn ném cái ổ khóa vào. Các kỹ sư hệ thống bị ép phải chọn một trong hai viên thuốc độc sau:</p>

<ul>
<li><strong>Khóa cấp độ Bảng (Table-Level Lock - Hạt to):</strong> Nếu bạn ném một cái ổ khóa khổng lồ khóa chặt toàn bộ bảng <code>users</code>, Database chỉ tốn đúng vài byte RAM để lưu trữ 1 cái khóa đó. Chi phí quản lý bộ nhớ gần như bằng 0. NHƯNG, tính đồng thời (Concurrency) bị tiêu diệt hoàn toàn. Nếu bạn đang cập nhật thông tin cho User 1, thì User 2, User 3, và User 1.000.000 đều bị block cứng ngắc, không ai được làm gì cả. Hệ thống của bạn trở thành một cái nút thắt cổ chai khổng lồ.</li>
<li><strong>Khóa cấp độ Hàng (Row-Level Lock - Hạt nhỏ):</strong> Để giải quyết nút thắt cổ chai trên, bạn chỉ đặt một ổ khóa siêu nhỏ vào đúng cái dòng (Row) mà bạn đang sửa. Tuyệt vời! Hàng triệu user khác có thể tha hồ update dữ liệu của họ cùng lúc. Tính đồng thời được tối đa hóa! NHƯNG, nếu một câu lệnh <code>UPDATE</code> của bạn vô tình chạm tới 1 triệu dòng dữ liệu (ví dụ: tăng 10% lương cho toàn bộ nhân viên), Database sẽ phải đẻ ra 1 TRIỆU cái ổ khóa riêng biệt trong RAM. CPU sẽ bị chết ngạt trong việc duy trì và quản lý 1 triệu cái khóa này, RAM sẽ cạn kiệt, và hệ thống sẽ sập (Out of Memory).</li>
</ul>

<p>Vậy các Database hiện đại (như MySQL InnoDB, PostgreSQL) làm sao để sống sót? Chúng dùng một kỹ thuật rủi ro gọi là <strong>Lock Escalation (Leo thang Khóa)</strong>. Ban đầu, Database sẽ cố gắng dùng Row Lock. Nhưng nếu nó thấy bạn tham lam khóa quá nhiều dòng (vượt một ngưỡng ví dụ 20% bảng), nó sẽ hoảng loạn (panic). Nó vứt bỏ toàn bộ hàng ngàn cái Row Lock đi, và mạnh tay đập một cái Table Lock siêu to khổng lồ khóa toàn bộ bảng lại để tự cứu lấy RAM của nó. Hệ quả? Câu query của bạn đột nhiên "leo thang" và khóa mõm toàn bộ hệ thống của công ty mà bạn không hề hay biết!</p>

<h2>Sự Thiên Tài Của "Khóa Ý Định" (Intention Locks)</h2>

<p>Hãy thử giải bài toán này: Nếu hệ thống đang dùng Row Lock, hàng vạn user đang chạy lăng xăng giữ hàng vạn cái khóa bên trong bảng <code>orders</code>. Đột nhiên, một gã Admin gõ lệnh <code>DROP TABLE orders</code>. Làm sao Database biết được bảng này đang an toàn để xóa? Chẳng lẽ nó phải scan qua toàn bộ 100 triệu dòng để kiểm tra xem có dòng nào đang bị khóa không? Quá trình scan đó sẽ tốn vài tiếng đồng hồ.</p>

<p>Jim Gray đã phát minh ra một giải pháp thiên tài mang tên <strong>Intention Locks (Khóa Ý định)</strong>. Quy luật rất đơn giản: Khi Transaction A muốn chui vào khóa một cái "Phòng" (Row Lock), nó bắt buộc phải treo một cái biển báo "Khóa Ý định" (IX Lock) ngay tại "Cửa chính của Tòa nhà" (Table Lock).</p>

<p>Cái biển báo IX ở cửa chính thông báo cho cả thế giới biết: <em>"Đang có người ở bên trong một căn phòng nào đó, làm ơn đừng giật sập tòa nhà này"</em>. Bây giờ, khi gã Admin chạy lệnh <code>DROP TABLE</code>, Database chỉ cần đi tới cửa chính của tòa nhà, nhìn thấy tấm biển IX, và lập tức từ chối lệnh DROP ngay trong 1 mili-giây, mà không cần mất công chạy đi kiểm tra từng căn phòng một.</p>

<h2>Bế Tắc (Deadlock): Bản Án Tử Hình Không Thể Tránh Khỏi</h2>

<p>Jim Gray cũng là người đã công thức hóa bài toán <strong>Deadlock (Khóa Chết)</strong> huyền thoại. Transaction A giữ chìa khóa số 1 và đứng chờ chìa khóa số 2. Transaction B giữ chìa khóa số 2 và đứng chờ chìa khóa số 1. Cả hai sẽ đứng trừng mắt nhìn nhau cho đến ngày tận thế. Về mặt toán học, bạn KHÔNG THỂ ngăn chặn hoàn toàn Deadlock xảy ra nếu bạn muốn hệ thống có hiệu năng cao.</p>

<p>Thay vì cố gắng ngăn chặn (Prevent), Gray đề xuất việc <strong>Phát hiện (Detection)</strong>. Các hệ quản trị CSDL có một con Bot chạy ngầm (Deadlock Detector). Nó liên tục vẽ ra một cái Biểu đồ chờ đợi (Wait-For Graph). Khi nó phát hiện ra một vòng lặp chết chóc (A chờ B, B chờ A), con Bot này sẽ đóng vai một tên đao phủ tàn nhẫn. Nó nhắm mắt chọn bừa một Transaction làm "Nạn nhân" (Victim), thẳng tay bóp cổ giết chết Transaction đó (Rollback), để giải phóng chìa khóa cho Transaction kia chạy tiếp.</p>

<p>Bài học cho kỹ sư phần mềm là gì? Đừng hy vọng Code của bạn không bao giờ bị Deadlock. Hãy chấp nhận sự thật tàn nhẫn rằng Database sẽ thỉnh thoảng "giết" các câu lệnh SQL của bạn một cách ngẫu nhiên. Công việc của bạn là phải viết Code Application có khả năng <code>catch</code> cái lỗi Deadlock đó, và <code>retry</code> (thử lại) một cách ngoan ngoãn.</p>

<h2>Lời Kết</h2>

<p>Concurrency không phải là một lỗi (bug) để fix; nó là một bài toán đánh đổi (trade-off) để quản trị. Bài báo của Jim Gray đã cung cấp cho chúng ta bộ từ vựng Toán học (Row locks, Table locks, Intention locks, Deadlocks) mà 50 năm sau chúng ta vẫn phải dùng hằng ngày. Sự khác biệt giữa một Dev Junior (viết những câu SQL làm sập hệ thống) và một System Architect (thiết kế hệ thống chịu tải hàng triệu TPS) nằm chính ở việc họ có thấu hiểu sâu sắc những giới hạn kiến trúc vật lý này hay không.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Đại Chiến Đồng Thời: Bài Toán Deadlock Và Nghệ Thuật Đặt Khóa (Locks)',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Locking', 'Jim Gray', 'Concurrency', 'Deadlock']
]);
pll_set_post_language($post_vi, 'vi');
set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>同時に動くことの恐怖（Concurrency Chaos） 😱</h2>

<p>想像してみてください。窓口が1つしかない銀行があります。2人の客がお金を預けに来たら、彼らは1列に並んで順番を待たなければなりません。これは非常に遅いですが、「絶対に安全」です。誰のお金も消えることはありません。</p>

<p>では、同じ銀行に「1000人の窓口係」がいて、全員が全く同じ瞬間に「同じ金庫」からお金を出し入れしようとしたらどうなるでしょうか？ 厳格なルールがなければ、金庫の残高は一瞬でメチャクチャに破壊されてしまいます。これが、データベースの世界における<strong>「並行処理（Concurrency）」</strong>の根本的な問題です。</p>

<p>データが破壊されるのを防ぐため、データベースは<strong>「ロック（鍵）」</strong>を使います。トランザクションA（処理A）がユーザーの残高を書き換えるとき、そのデータに「排他ロック（他の人は触るなという鍵）」をかけます。もしトランザクションBも同じデータを書き換えたい場合、Aの処理が終わるまで順番を待たなければなりません。言葉にするとシンプルですが、1975年にジム・グレイ（Jim Gray：後にチューリング賞を受賞）が発表した論文<em>『Granularity of Locks and Degrees of Consistency in a Shared Database（共有データベースにおけるロックの粒度と一貫性の度合い）』</em>は、この「鍵の管理」が、実は恐ろしいほど複雑な数学的バランスゲームであることを明らかにしました。</p>

<h2>究極のジレンマ：テーブル全体をロックするか、行だけをロックするか 🔑</h2>

<p>グレイの論文は、<strong>「ロックの粒度（Lock Granularity：鍵をかける範囲の大きさ）」</strong>という概念を定式化しました。データベースの設計者たちは、残酷なトレードオフ（あちらを立てればこちらが立たず）に直面します。</p>

<ul>
<li><strong>テーブルロック（粒度が粗い・デカい鍵）：</strong> もし <code>users</code> テーブル全体に巨大な鍵を1つかけた場合、データベースがメモリ上に管理する鍵は「たった1個」で済みます。メモリの消費量はほぼゼロです。しかし、並行処理（同時に動くこと）は完全に死にます。あなたがユーザー1のデータを更新している間、他の誰もユーザー2やユーザー100万のデータを触ることができません。巨大なシステムが、たった1列の行列になってしまうのです。</li>
<li><strong>行ロック（粒度が細かい・小さな鍵）：</strong> テーブル全体の代わりに、書き換えたい「たった1行」にだけ極小の鍵をかけたらどうでしょう？ 素晴らしい！ 他の100万人のユーザーは、自分のデータを同時に更新できます。並行処理は最高潮に達します！ しかし、メモリ消費量は壊滅的になります。もしあなたの処理が（例えば全社員の給料を一斉に上げるために）100万行のデータを更新しようとした場合、データベースはRAMの中に「100万個の独立した小さな鍵」を作り、それを管理しなければなりません。CPUはデータを書き込む作業を放棄し、鍵の管理だけで窒息死してしまうでしょう。</li>
</ul>

<p>現代のデータベースはどうやってこのジレンマを解決しているのでしょうか？ 彼らは<strong>「ロックのエスカレーション（Lock Escalation：鍵の巨大化）」</strong>という危険な魔法を使います。最初は小さな「行ロック」をたくさん使って処理を始めます。しかし、鍵の数が一定の限界（例えばテーブルの20%）を超えた瞬間、データベースはパニックを起こします。「もう無理だ！」と叫び、すべての小さな鍵を放り投げ、突然「巨大なテーブルロック」へと強制アップグレードしてしまうのです。この瞬間、あなたのちょっとしたクエリが、他の全ユーザーの画面をフリーズさせてしまう大惨事を引き起こすことがあります。</p>

<h2>「インテンション・ロック（意図の鍵）」という天才的な発明 💡</h2>

<p>もしシステムが小さな「行ロック」を採用していた場合、恐ろしい問題が発生します。システム管理者が <code>DROP TABLE users</code>（テーブルそのものの削除）を実行しようとしたとします。データベースは、「現在、誰かがテーブルの中で行ロックを握りしめて作業していないか」を絶対に確認しなければなりません。</p>

<p>データベースは、削除する前に1億行のデータすべてをスキャンして、どこかに小さな鍵がかかっていないか探さなければならないのでしょうか？ そんなことをしたら何時間もかかってしまいます。</p>

<p>ジム・グレイは、この問題を解決するために<strong>「インテンション・ロック（意図の鍵：IX Lock）」</strong>という天才的な仕組みを発明しました。ルールは簡単です。「建物（テーブル）の中にある個別の部屋（行）に鍵をかけたい人は、必ず建物の正面玄関（テーブル）に『誰かが部屋で工事中だよ』という意図を示す看板（IX Lock）をぶら下げなければならない」というものです。</p>

<p>これで問題は解決です！ 管理者が建物を爆破（テーブル削除）しようとしたとき、データベースは正面玄関の看板（IX Lock）をチラッと見るだけで、「あ、誰かが中にいるから今は爆破できないな」と1ミリ秒で即座に拒否できるのです。1億個の部屋をいちいちノックして回る必要はありません。</p>

<h2>デッドロック（死の抱擁）：数学的に解決不可能な膠着状態 💀</h2>

<p>グレイはまた、<strong>「デッドロック（Deadlock）」</strong>という概念も定式化しました。トランザクションAは鍵1を握りしめたまま、鍵2が空くのを待っています。トランザクションBは鍵2を握りしめたまま、鍵1が空くのを待っています。彼らは永遠に互いを見つめ合ったまま、宇宙が終わるまでフリーズし続けます。並行処理を許す以上、これを数学的に完全に防ぐ方法はありません。</p>

<p>防ぐことができないなら、どうするか？ グレイは<strong>「死の検出（Deadlock Detection）」</strong>を提案しました。データベースの裏側では、「誰が誰の鍵を待っているか」という関係図（Wait-For Graph）を描き続ける死神のアルゴリズムが常に走っています。この死神が「おや？ こいつら永遠に待ち合うループに入ったな」と検出した瞬間、死神はランダムに片方のトランザクションを「犠牲者（Victim）」として選び、<strong>容赦なくその処理を強制終了（Kill）させ、変更をロールバック（巻き戻し）し、鍵を奪い取って</strong>、もう片方を先に進ませます。</p>

<p>私たちプログラマーが学ぶべき教訓は何でしょうか？ それは「デッドロックはバグではなく、避けられない自然現象である」ということです。私たちが書くプログラムは、時々データベースの死神によってランダムに殺されます。だからこそ、エラーを検知して「もう一度最初からやり直す（Retry）」というコードを必ず書いておかなければならないのです。</p>

<h2>学んだこと</h2>

<p>並行処理（Concurrency）は「解決」できる問題ではありません。それは「管理すべきトレードオフ（バランス）」なのです。ジム・グレイの論文は、行ロック、テーブルロック、インテンション・ロック、デッドロックといった、50年経った今でも私たちが毎日使っている「数学的な語彙」を与えてくれました。これらの物理的・数学的な限界を深く理解しているかどうかこそが、「システムを頻繁にフリーズさせる初心者」と「毎秒何百万トランザクションをさばける凄腕アーキテクト」を分ける決定的な境界線なのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => '並行処理のパニック：デッドロックと「鍵の粒度」の芸術',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Locking', 'Jim Gray', 'Concurrency', 'Deadlock']
]);
pll_set_post_language($post_ja, 'ja');
set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Cluster 13 (Lock Granularity) with Categories, Tags, and Translation Links!\n";
