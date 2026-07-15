<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'aries_recovery.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
copy($image_path, $destination);
$filetype = wp_check_filetype($filename, null);
$attachment = [
    'post_mime_type' => $filetype['type'],
    'post_title'     => 'ARIES Recovery',
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

$cat_en = setup_term('Database Reliability', 'category', 'en');
$cat_vi = setup_term('Độ Tin Cậy Hệ Thống', 'category', 'vi');
$cat_ja = setup_term('データベースの信頼性', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>The Ultimate Nightmare: The Power Cord is Pulled</h2>

<p>Every software developer lives in fear of the random crash. But for database engineers, a crash is not just an inconvenience; it is an existential threat. Imagine a bank processing 10,000 transactions per second. In the middle of transferring $1,000 from Alice to Bob, the database server loses power. The CPU turns off instantly. The RAM is completely wiped.</p>

<p>When the server boots back up, what state is the database in? Did Alice lose her money without Bob receiving it? Did the database write half a row to the hard drive? If the database is corrupted, the bank is destroyed.</p>

<p>To survive this, a database must guarantee "Durability" (the \'D\' in ACID). In 1992, C. Mohan at IBM published a seminal paper detailing the exact algorithm that prevents data loss: <em>"ARIES: A Transaction Recovery Method"</em>. Today, almost every major relational database on Earth uses a variant of the ARIES algorithm to resurrect itself from the ashes of a crash.</p>

<h2>The Buffer Pool: Steal and No-Force</h2>

<p>Before understanding ARIES, you have to understand the fundamental tension between Performance and Safety. Databases do not write data directly to the disk. Disks are too slow. Instead, they write data into a chunk of RAM called the "Buffer Pool".</p>

<p>Database architects face two major policy decisions regarding this Buffer Pool:</p>
<ol>
<li><strong>Force vs. No-Force:</strong> If a transaction successfully commits, do we <em>Force</em> the database to immediately write those specific pages to the hard drive? Forcing it is safe, but causes terrible Random I/O. Therefore, databases use a <strong>No-Force</strong> policy. They leave the committed data in RAM and lazily write it to disk later. But this means if the power goes out, committed data in RAM is lost!</li>
<li><strong>No-Steal vs. Steal:</strong> If the RAM gets full, can the database take an <em>uncommitted</em> transaction\'s data and flush it to the disk to make room? (This is called "Stealing" a frame). If you don\'t Steal, transactions are limited by the size of your RAM. So databases use a <strong>Steal</strong> policy. But this means if the power goes out, your hard drive is polluted with dirty, half-finished data!</li>
</ol>

<p>Because databases use the "Steal / No-Force" policy for maximum performance, a crashed database is guaranteed to be in a state of absolute chaos. It is missing committed data, and it is infected with uncommitted data.</p>

<h2>The Magic of the Write-Ahead Log (WAL)</h2>

<p>How does ARIES solve this chaos? By using a <strong>Write-Ahead Log (WAL)</strong>. The WAL is a simple, append-only file on the disk. Before the database is allowed to modify any data in the RAM Buffer Pool, it must first write a description of the change to the WAL on disk. Because the WAL is append-only, writing to it is extremely fast (Sequential I/O).</p>

<p>Every single action is recorded in the WAL with a unique Log Sequence Number (LSN). "Transaction 1 changed Row A from 10 to 5." If the power goes out, the WAL is the indestructible black box of the airplane. It contains the exact history of everything that happened.</p>

<h2>The Three Phases of Resurrection</h2>

<p>When the database reboots after a crash, the ARIES algorithm kicks in. It ignores the corrupted data files entirely and turns to the WAL. It performs a magical three-step resurrection:</p>

<h3>1. The Analysis Phase</h3>
<p>ARIES scans the WAL from the last known checkpoint to the end. It figures out exactly which transactions successfully committed before the crash, and which transactions were still running (and therefore need to be rolled back). It builds a map of the chaos.</p>

<h3>2. The REDO Phase (Repeating History)</h3>
<p>This is where ARIES differs from older algorithms. It scans the WAL again and simply <strong>replays every single action</strong> exactly as it happened, including the actions of transactions that eventually failed! It literally reconstructs the exact state of the RAM Buffer Pool at the exact millisecond the power went out. By the end of the REDO phase, the database is back to the exact chaotic state it was in before the crash.</p>

<h3>3. The UNDO Phase</h3>
<p>Now that the RAM is restored to the moment of the crash, ARIES looks at the map it built in Phase 1. It identifies the "loser" transactions (the ones that never committed) and reads their WAL logs backwards. For every change they made, it executes the mathematical opposite (e.g., if it added $500, ARIES subtracts $500). It carefully unweaves the uncommitted data from the system.</p>

<h2>The Unsung Hero</h2>

<p>When your database survives a server crash and boots up perfectly fine 30 seconds later, you are witnessing a miracle of computer science. You are witnessing the ARIES algorithm meticulously reading its own diary, rebuilding the past, and erasing its mistakes. It is the unsung hero that allows the modern digital economy to sleep soundly at night.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'Resurrection from the Ashes: How the ARIES Algorithm Survives Power Outages',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['ARIES', 'WAL', 'Recovery', 'Durability']
]);
pll_set_post_language($post_en, 'en');
set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>Cơn Ác Mộng Của Mọi Kỹ Sư: Mất Điện Đột Ngột</h2>

<p>Bất kỳ một Lập trình viên nào cũng từng trải qua cảm giác tim đập chân run khi ứng dụng bị Crash. Nhưng đối với một Database Engineer (Kỹ sư CSDL), một cú Crash không chỉ là một lỗi phần mềm; nó là một thảm họa mang tính sinh tử. Hãy tưởng tượng bạn đang quản lý Database cho một ngân hàng xử lý 10.000 giao dịch mỗi giây. Ngay giữa lúc hệ thống đang trừ 1.000 USD của khách hàng A và chuẩn bị cộng 1.000 USD cho khách hàng B, nguồn điện của Data Center đột ngột bị cắt.</p>

<p>CPU sập nguồn ngay tức khắc. Toàn bộ dữ liệu nằm trong RAM bốc hơi không để lại một dấu vết. Vài phút sau, khi máy chủ khởi động lại, câu hỏi sống còn được đặt ra: Dữ liệu đang ở trạng thái nào? Tiền của khách hàng A đã biến mất mà khách hàng B chưa nhận được? Hay tồi tệ hơn, ổ cứng chỉ mới kịp ghi một nửa dòng dữ liệu thì tắt thở? Nếu Database bị hỏng (Corrupted), ngân hàng đó coi như phá sản.</p>

<p>Để sống sót qua những thảm họa vật lý này, Database phải đảm bảo được chữ \'D\' (Durability - Tính Bền Vững) trong nguyên tắc ACID. Năm 1992, nhà nghiên cứu C. Mohan tại IBM đã công bố một thuật toán huyền thoại, trở thành bản thiết kế chuẩn mực cho việc phục hồi dữ liệu: <em>"ARIES: A Transaction Recovery Method"</em>. Ngày nay, mọi hệ quản trị CSDL quan hệ lớn nhất thế giới (PostgreSQL, SQL Server, DB2) đều đang sử dụng một biến thể của ARIES để hồi sinh dữ liệu từ cõi chết.</p>

<h2>Nút Thắt Cổ Chai: Hiệu Năng vs An Toàn (Steal / No-Force)</h2>

<p>Để hiểu sự vĩ đại của ARIES, bạn phải hiểu được sự giằng xé tàn nhẫn giữa Hiệu năng (Performance) và Tính an toàn (Safety). Database không bao giờ ghi dữ liệu thẳng xuống ổ đĩa, vì ổ đĩa quá chậm. Nó ghi dữ liệu vào một vùng RAM gọi là "Buffer Pool" (Bể đệm).</p>

<p>Các kiến trúc sư Database phải đối mặt với 2 quyết định sinh tử về cái Buffer Pool này:</p>
<ol>
<li><strong>Ép ghi (Force) vs Không ép ghi (No-Force):</strong> Khi một giao dịch báo thành công (Commit), ta có nên ÉP Database phải ghi ngay cục RAM đó xuống ổ đĩa không? Ép ghi thì cực kỳ an toàn, nhưng nó tạo ra hàng vạn thao tác Random I/O bóp nghẹt ổ cứng. Do đó, các Database hiện đại chọn chính sách <strong>No-Force</strong>. Chúng cứ để dữ liệu đã commit trên RAM, và lười biếng ghi xuống đĩa sau. Đánh đổi lại: Nếu mất điện, dữ liệu đã Commit trên RAM sẽ tan thành mây khói!</li>
<li><strong>Ăn trộm (Steal) vs Không ăn trộm (No-Steal):</strong> Nếu RAM bị đầy, Database có được phép lấy dữ liệu của một giao dịch <em>đang chạy dở dang (chưa Commit)</em> ném tạm xuống đĩa cứng để lấy chỗ trống không? Nếu cấm điều này (No-Steal), các giao dịch lớn sẽ làm tràn RAM và Crash. Do đó, Database chọn chính sách <strong>Steal</strong>. Đánh đổi lại: Nếu mất điện, ổ cứng của bạn lúc này đang bị dính đầy những dữ liệu rác, dở dang, chưa hoàn tất của các giao dịch bị đứt gánh!</li>
</ol>

<p>Vì Database bắt buộc phải chọn "Steal / No-Force" để tối đa hóa hiệu năng, nên về mặt vật lý, một Database vừa bị mất điện chắc chắn sẽ ở trong tình trạng <strong>vô cùng hỗn loạn và rác rưởi</strong>. Nó vừa bị mất những dữ liệu quan trọng, vừa bị nhiễm độc bởi những dữ liệu dở dang.</p>

<h2>Chiếc Hộp Đen Máy Bay: Write-Ahead Log (WAL)</h2>

<p>Vậy ARIES dọn dẹp bãi rác này như thế nào? Bằng một công cụ gọi là <strong>Write-Ahead Log (Nhật ký Ghi trước - WAL)</strong>. WAL là một file text đơn giản nằm trên ổ đĩa. Quy luật thép của Database là: Trước khi mày được phép sửa bất kỳ 1 byte dữ liệu nào trên RAM, mày BẮT BUỘC phải ghi lại hành động đó vào file WAL trên đĩa cứng.</p>

<p>Bởi vì WAL chỉ là hành động "Ghi nối thêm vào đuôi file" (Append-only), tốc độ ghi của nó là Sequential I/O (nhanh gấp ngàn lần Random I/O). Mọi thao tác đều được ghi lại với một số thứ tự (LSN - Log Sequence Number). Ví dụ: "LSN 100: Transaction 1 sửa Số dư từ 10 thành 5". Nếu máy chủ mất điện, file WAL chính là chiếc Hộp Đen bất hoại của máy bay. Nó chứa đựng cuốn băng ghi hình chính xác mọi thứ đã xảy ra.</p>

<h2>Ba Giai Đoạn Hồi Sinh Từ Cõi Chết</h2>

<p>Khi máy chủ có điện trở lại, thay vì hoảng loạn nhìn vào mớ dữ liệu rác trên ổ cứng, thuật toán ARIES bình tĩnh mở cuốn nhật ký WAL ra và thực hiện 3 phép thuật hồi sinh:</p>

<h3>1. Giai đoạn Phân tích (Analysis Phase)</h3>
<p>ARIES đọc lướt cuốn WAL từ điểm Checkpoint cuối cùng. Nó khoanh vùng và lập ra một danh sách tử thần: Giao dịch nào đã Commit thành công trước lúc mất điện (những kẻ chiến thắng), và giao dịch nào đang chạy dở dang thì bị cắt điện (những kẻ thua cuộc cần phải bị xóa bỏ).</p>

<h3>2. Giai đoạn REDO (Lặp lại Lịch sử)</h3>
<p>Đây là điểm khác biệt thiên tài của ARIES so với các thuật toán cũ. Nó đọc WAL từ đầu đến cuối và <strong>nhắm mắt làm lại TẤT CẢ mọi thao tác</strong> y hệt như những gì đã xảy ra, bao gồm cả những thao tác của bọn thua cuộc! ARIES thực chất đang tái tạo lại chính xác 100% tình trạng hỗn loạn của cái RAM Buffer Pool tại đúng cái mili-giây mà Data Center bị cắt điện.</p>

<h3>3. Giai đoạn UNDO (Sửa sai)</h3>
<p>Bây giờ, khi bộ nhớ RAM đã được khôi phục lại khoảnh khắc trước khi chết, ARIES lôi cái danh sách "những kẻ thua cuộc" ở bước 1 ra. Nó đọc ngược cuốn nhật ký WAL từ dưới lên trên. Với mỗi hành động của bọn thua cuộc, ARIES thực hiện một phép toán NGƯỢC LẠI (Ví dụ: hồi nãy cộng 500 thì giờ trừ đi 500). Nó tỉ mỉ tháo gỡ từng sợi chỉ rác rưởi của các giao dịch dở dang ra khỏi hệ thống.</p>

<h2>Người Hùng Thầm Lặng</h2>

<p>Khi Server của công ty bạn bị sập nguồn, và 30 giây sau nó tự động boot lên, Database hiển thị trạng thái "Ready" (Sẵn sàng) và không suy suyển 1 byte dữ liệu nào—đó không phải là phép màu. Đó là bạn đang chứng kiến thuật toán ARIES tận tụy mở cuốn nhật ký của chính nó ra, dựng lại một bộ phim về quá khứ, và tự tay xóa đi những sai lầm. Nó chính là người hùng thầm lặng giúp cho nền kinh tế số hàng ngàn tỷ đô la của nhân loại có thể ngủ ngon mỗi đêm.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Phục Sinh Từ Đống Tro Tàn: Cách Thuật Toán ARIES Cứu Rỗi Dữ Liệu Khi Mất Điện',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['ARIES', 'WAL', 'Recovery', 'Durability']
]);
pll_set_post_language($post_vi, 'vi');
set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>すべてのエンジニアの悪夢：突然の電源喪失 🌩️🔌</h2>

<p>プログラムがクラッシュして画面が真っ暗になるのは、誰にとっても嫌な経験です。しかし、データベース・エンジニアにとって「クラッシュ」は単なる不便ではありません。それは、会社の存続を脅かす死活問題です。</p>

<p>毎秒1万件の取引を処理している巨大な銀行のデータベースを想像してください。アリスの口座から1000ドルを引き落とし、ボブの口座に1000ドルを追加しようとしているまさにそのミリ秒の瞬間に、データセンターに落雷があり、サーバーの電源が完全に落ちてしまいました。CPUは即座に停止し、RAM（メモリ）上のデータは跡形もなく消え去ります。</p>

<p>数分後、予備電源でサーバーが再起動したとき、データベースは一体どうなっているでしょうか？ アリスのお金だけが引き落とされて虚空に消え、ボブには届いていないのでしょうか？ もしデータベースの整合性が壊れて（Corrupted）しまっていたら、その銀行は翌日には破産してしまうでしょう。</p>

<p>このような物理的な大災害から生き残るため、データベースは絶対に「耐久性（Durability：ACIDの \'D\'）」を保証しなければなりません。1992年、IBMのC. モハン（C. Mohan）が、このデータ喪失を防ぐための完璧なアルゴリズムを記した論文を発表しました。それが<em>『ARIES: A Transaction Recovery Method（トランザクション回復手法 ARIES）』</em>です。今日、地球上に存在するほぼすべての主要なデータベース（PostgreSQLやSQL Serverなど）は、クラッシュの灰の中からデータを蘇らせるために、このARIESアルゴリズムの恩恵を受けています。</p>

<h2>パフォーマンスと安全性のジレンマ（Steal と No-Force） ⚖️</h2>

<p>ARIESの魔法を理解する前に、データベースが抱える「パフォーマンスと安全性の残酷なトレードオフ」を理解する必要があります。データベースは、データを直接ハードディスクに書き込むことはしません。ディスクは遅すぎるからです。代わりに、データを一度RAM上の「バッファプール（Buffer Pool）」という領域に書き込みます。</p>

<p>データベースの設計者は、このバッファプールの扱いについて、2つの重大な決断を迫られます：</p>
<ol>
<li><strong>強制書き込み（Force）か、しない（No-Force）か：</strong> トランザクションが成功（Commit）した瞬間、そのデータを「今すぐ絶対にディスクに書き込め（Force）」と強制すべきでしょうか？ これは非常に安全ですが、激しいランダム書き込みが発生し、システムが激重になります。そのため、現代のデータベースは<strong>「No-Force（強制しない）」</strong>を選びます。データはRAMに置いたままにしておき、後で暇なときにまとめてディスクに書き込みます。しかしこれは、<strong>「もし今停電したら、成功したはずのデータがRAMから消滅してしまう！」</strong>という恐ろしいリスクを抱えることになります。</li>
<li><strong>横取り（Steal）するか、しない（No-Steal）か：</strong> もしRAMがいっぱいになってしまったとき、データベースは「まだ処理中（未コミット）」の他人のデータをこっそりディスクに追い出して（Stealして）、場所を空けてもよいでしょうか？ もしダメ（No-Steal）だとしたら、巨大な処理を実行した瞬間にRAMが溢れてクラッシュします。だからデータベースは<strong>「Steal（横取りを許可する）」</strong>を選びます。しかしこれは、<strong>「もし今停電したら、ディスクの中に『未完成のゴミデータ』が混入したままになってしまう！」</strong>という最悪の事態を意味します。</li>
</ol>

<p>データベースは処理速度を限界まで引き上げるために、あえて危険な「Steal / No-Force」という方針を採用しています。つまり、停電直後のデータベースは、<strong>「必要なデータが欠落しており、未完成のゴミデータが混入している」という絶望的なカオス状態</strong>にあることが数学的に確定しているのです。</p>

<h2>最強のブラックボックス「先行書き込みログ（WAL）」 🗃️</h2>

<p>このカオスを、ARIESはどうやって解決するのでしょうか？ その答えが<strong>「WAL（Write-Ahead Log：先行書き込みログ）」</strong>です。</p>

<p>WALは、ディスク上にある単純なテキストファイルです。データベースには絶対に破ってはいけない鉄の掟があります。「RAMのデータを書き換える前に、必ずその『変更内容』をディスク上のWALファイルにメモ（先行書き込み）しなければならない」。WALはファイルの末尾に追記（Append）していくだけなので、ディスクへの書き込み速度（シーケンシャルI/O）は爆発的に速く、パフォーマンスの邪魔になりません。</p>

<p>すべての操作は、一意の番号（LSN：ログシーケンス番号）と共にWALに記録されます。「LSN 100: トランザクション1が、残高を10から5に変更した」。もし停電が起きても、このWALファイルだけは飛行機の「フライトレコーダー（ブラックボックス）」のように無傷で残ります。ここには、過去に起きたすべての真実が記録されているのです。</p>

<h2>蘇生のための3つの魔法（フェーズ） 🪄</h2>

<p>クラッシュ後、サーバーが再起動すると、ARIESアルゴリズムが目覚めます。ARIESはディスク上の壊れたデータファイルを見向きもせず、真っ直ぐにWALファイルを開き、3つの魔法のステップでデータベースを蘇生させます。</p>

<h3>1. 分析フェーズ（Analysis Phase）</h3>
<p>ARIESはWALをざっと読み込み、状況を把握します。「停電の瞬間に、どの処理がすでに完了（コミット）していて、どの処理がまだ実行中（未コミットの敗者）だったのか」を完全にリストアップし、カオスの見取り図を作成します。</p>

<h3>2. REDOフェーズ（歴史の完全な再現）</h3>
<p>ここがARIESの最もクレイジーで天才的な部分です。ARIESはWALを最初から読み直し、<strong>過去に起きたすべてのアクションを、もう一度そのまま物理的にやり直す（REDO）</strong>のです。驚くべきことに、最終的に失敗して取り消されるべき「敗者」の操作でさえも、忠実にやり直します。ARIESは実質的に、停電が起きた「まさにそのミリ秒」のRAMのカオス状態を、そっくりそのままメモリ上に再構築しているのです。</p>

<h3>3. UNDOフェーズ（過ちの消去）</h3>
<p>RAMが「停電直前の状態」に完全に戻ったところで、ARIESはステップ1で作った「未コミットの敗者リスト」を取り出します。そして、WALを「後ろから逆向き（下から上）」に読みながら、敗者たちが行った操作の「数学的な逆の操作」を適用していきます。（例：500足していたら、500引く）。こうして、途中で途切れてしまった未完成のゴミデータだけを、システムから綺麗に解きほぐして消し去るのです。</p>

<h2>縁の下の英雄 🦸‍♂️</h2>

<p>あなたの会社のデータベースが突然の電源喪失から生き残り、30秒後に何事もなかったかのように正常に起動したとき、あなたはコンピュータサイエンスの奇跡を目の当たりにしています。それは、ARIESアルゴリズムが自らの日記（WAL）を読み返し、失われた過去の歴史を一度完全に再現し、そして自らの過ちだけを丁寧に消し去るという、途方もない作業を瞬時に終わらせた結果なのです。私たちが夜、安心して眠りにつくことができるのは、この縁の下の英雄のおかげなのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => '灰の中からの復活：ARIESアルゴリズムは停電からどうやってデータを救うのか',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['ARIES', 'WAL', 'Recovery', 'Durability']
]);
pll_set_post_language($post_ja, 'ja');
set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Cluster 14 (ARIES Recovery) with Categories, Tags, and Translation Links!\n";
