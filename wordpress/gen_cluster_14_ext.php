<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'aries_recovery_1784014302142.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
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
} else {
    $attach_id = 0;
}

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
$cat_vi = setup_term('Độ Tin Cậy Database', 'category', 'vi');
$cat_ja = setup_term('データベースの信頼性', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>AI Summary / Executive Abstract</h2>
<ul>
<li><strong>What is it?</strong> Published in 1992 by C. Mohan at IBM, "ARIES" (Algorithm for Recovery and Isolation Exploiting Semantics) is the mathematical bedrock of database crash recovery. It guarantees the "D" (Durability) and "A" (Atomicity) in ACID transactions.</li>
<li><strong>The Core Problem:</strong> If a database server loses power in the middle of a massive transaction, the hard drive is left in a corrupted state. Some data was written, some wasn\'t. Before ARIES, fixing this required either painfully slow "Force" writing to disk, or locking users out for hours while the database slowly reconstructed the truth.</li>
<li><strong>The Solution:</strong> ARIES formalized <strong>Write-Ahead Logging (WAL)</strong> and the <strong>Steal/No-Force</strong> buffer pool policy. When a transaction occurs, the database only writes a tiny append-only "Log Record" to disk, leaving the actual heavy data in RAM to be written later lazily. If the power fails, ARIES uses a brilliant 3-pass algorithm (Analysis, Redo, Undo) to scan the log and perfectly rebuild the database exactly as it was.</li>
<li><strong>Modern Reality:</strong> Every major database today (PostgreSQL, SQL Server, IBM Db2, MySQL InnoDB) uses ARIES or a direct derivative. It is the reason your bank account balance survives server blackouts.</li>
</ul>

<h2>Historical Context & The Catalyst: The Power Outage Nightmare</h2>
<p>To understand the genius of ARIES, you must imagine a catastrophic failure. A bank is processing a $1,000,000 transfer from Account A to Account B. The database deducts $1,000,000 from Account A (in RAM). Then, an administrator trips over the power cord. The server goes dead.</p>

<p>When the server reboots, what state is the hard drive in?</p>
<p>In the 1970s and 80s, database architects faced a brutal dilemma regarding when to physically write RAM data to the hard drive:</p>
<ol>
<li><strong>Force Policy:</strong> Force the database to write <em>everything</em> to disk before telling the user "Transaction Successful." This is perfectly safe. But because disk I/O is random and slow, transaction speed drops to 10 per second. The business dies from slowness.</li>
<li><strong>No-Force Policy:</strong> Let the database keep the modified data in fast RAM and tell the user "Success!" instantly. Write the data to disk later in the background. But if the power fails before the background write happens, the data is lost forever. The business dies from lawsuits.</li>
</ol>

<p>Furthermore, what if the database decides to flush RAM to disk <em>while</em> a transaction is still running (this is called a <strong>Steal</strong> policy)? If the power fails, the hard drive now contains half-finished, corrupted garbage.</p>
<p>The industry needed an algorithm that allowed for <strong>Steal / No-Force</strong> (maximum speed, maximum flexibility) while guaranteeing 100% crash recovery. C. Mohan delivered ARIES.</p>

<h2>The Academic Breakthrough: Write-Ahead Logging (WAL) and LSNs</h2>
<p>Mohan\'s breakthrough relied on two foundational pillars: The Write-Ahead Log (WAL) and the Log Sequence Number (LSN).</p>

<p>The <strong>WAL rule</strong> is simple but unbreakable: <em>You must write the intention to change data to the log on disk BEFORE you write the actual data to the disk.</em></p>
<p>The Log is just an append-only text file. Writing to the end of a file (Sequential I/O) is incredibly fast on a hard drive. So, when the bank transfer happens, the database instantly appends a tiny record to the WAL: <code>[Update Account A: -1,000,000]</code>. It then tells the user "Success!" and leaves the actual heavy database file modification in RAM for later (No-Force).</p>

<p>Every single action is assigned a monotonically increasing number: the <strong>LSN (Log Sequence Number)</strong>. If the server crashes, the WAL serves as an indestructible, perfectly ordered chronological history of everything that happened.</p>

<h2>Deep Architectural Walkthrough: The 3-Pass Recovery Dance</h2>
<p>The true magic of ARIES is what happens when the server reboots after the power cord is plugged back in. The database refuses to accept user connections. It enters "Crash Recovery Mode" and executes a strict 3-pass algorithm over the WAL.</p>

<h3>Pass 1: Analysis (What is the damage?)</h3>
<p>ARIES scans the WAL forward from the last known good checkpoint. It figures out exactly which transactions were active at the moment of the crash (the "Losers") and which ones successfully committed (the "Winners"). It also identifies the exact LSN where the disk corruption started (the "Dirty Page Table").</p>

<h3>Pass 2: Redo (Repeating History)</h3>
<p>This is Mohan\'s most counter-intuitive stroke of genius: <strong>Repeating History, Even the Mistakes</strong>.</p>
<p>ARIES scans the log forward again. It literally replays <em>every single action</em> in the log, regardless of whether the transaction was a Winner or a Loser. It forces the database back into the exact same chaotic, half-finished state it was in the millisecond before the power cord was pulled. Why? Because it restores the physical reality of the B-Trees and disk pages, making the next step mathematically foolproof.</p>

<h3>Pass 3: Undo (Fixing the Mistakes)</h3>
<p>Now that history has been repeated, ARIES scans the log <em>backwards</em>. For every "Loser" transaction identified in Pass 1, ARIES looks at the log record (e.g., <code>[Added $1,000,000 to Account B]</code>) and executes the exact inverse operation (<code>[Subtract $1,000,000 from Account B]</code>). By the time the backwards scan reaches the beginning, all half-finished transactions have been surgically erased.</p>

<p>To ensure that a second power failure during the Undo phase doesn\'t break the system, ARIES writes "Compensation Log Records" (CLRs) as it undoes things. This guarantees the recovery process is <em>idempotent</em>—you can crash during recovery, reboot, and ARIES will pick up right where it left off.</p>

<h2>Modern Production Reality: SSDs and Replication</h2>
<p>For 30 years, ARIES has reigned supreme. However, modern hardware is shifting the landscape.</p>

<p>ARIES was heavily optimized to minimize random disk reads during recovery, because random reads on magnetic HDDs take 10ms. On modern NVMe SSDs, random reads take 0.05ms. Because of this, some researchers argue that the strict "Repeating History" Redo pass is no longer strictly necessary, and modern systems like Microsoft\'s "Constant Time Recovery" are attempting to bypass it to allow users to connect to the database <em>during</em> the Undo phase.</p>

<p>Furthermore, the WAL designed by ARIES has taken on a second life. Today, databases use the WAL not just for crash recovery, but for <strong>Replication</strong>. When a PostgreSQL Primary server writes to its WAL, it streams that exact log over the network to a Replica server. The Replica simply runs the ARIES "Redo" phase continuously, keeping it perfectly synchronized with the Primary.</p>

<h2>Expert Critique & Legacy</h2>
<p>C. Mohan\'s ARIES paper is notoriously dense (it is 30 pages of dense algorithmic proofs), but it is a monumental achievement in computer science. Before ARIES, database crash recovery was a black art full of edge-case bugs and data corruption. ARIES turned it into a rigorous, mathematical certainty.</p>

<p>When you swipe your credit card, you are trusting the ARIES algorithm. It is the ultimate safety net of the digital world. It proves that with a sequential log and a disciplined recovery protocol, software can survive the chaotic, unpredictable physical failures of the real world without losing a single byte of truth.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'ARIES Recovery: How Databases Survive Sudden Power Outages',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['C. Mohan', 'ARIES', 'Crash Recovery', 'Write-Ahead Log', 'ACID']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Bài báo này là gì?</strong> Được xuất bản năm 1992 bởi C. Mohan tại IBM, "ARIES" là thuật toán phục hồi sau sự cố (Crash Recovery) vĩ đại nhất mọi thời đại. Nó là nền tảng toán học bảo vệ chữ "D" (Durability - Bền vững) và chữ "A" (Atomicity - Nguyên tử) trong chuẩn ACID của mọi Database.</li>
<li><strong>Vấn đề giải quyết:</strong> Nếu máy chủ đang xử lý chuyển tiền ngân hàng thì bị cúp điện đột ngột, ổ cứng sẽ rơi vào trạng thái "nửa vời" (Corrupted): Tiền đã trừ ở người gửi nhưng chưa kịp cộng cho người nhận. Trước khi có ARIES, để chống mất dữ liệu, DB phải bắt ổ cứng ghi ngay lập tức (Force) khiến hệ thống chậm như rùa, hoặc nếu cúp điện thì phải khóa DB hàng giờ liền để sửa tay.</li>
<li><strong>Giải pháp (Workflow):</strong> ARIES đưa ra luật <strong>Write-Ahead Logging (WAL)</strong>. Database chỉ cần ghi một dòng nhật ký (Log) bé xíu xiu nối đuôi vào ổ cứng (nhanh như chớp), và để dữ liệu thật nằm trên RAM từ từ ghi sau (No-Force). Nếu cúp điện, ARIES dùng 3 bước quét Log: Analysis (Phân tích), Redo (Làm lại lịch sử), và Undo (Hoàn tác lỗi) để khôi phục ổ cứng về trạng thái hoàn hảo chính xác từng byte.</li>
<li><strong>Thực tiễn Production:</strong> Thuật toán ARIES chính là phép màu giữ cho tài khoản ngân hàng của bạn không bị bốc hơi khi Data Center bị cháy. Ngày nay, Postgres, MySQL InnoDB, SQL Server đều dùng nguyên bản hoặc biến thể của ARIES.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Cơn Ác Mộng Sập Nguồn Cúp Điện</h2>
<p>Để hiểu sự thiên tài của ARIES, hãy tưởng tượng bạn là Giám đốc IT của một ngân hàng vào thập niên 1980. Có một Giao dịch (Transaction) đang chuyển 1 tỷ đồng từ tài khoản A sang tài khoản B. Database vừa trừ 1 tỷ của A trên RAM. Đúng lúc đó, ông lao công lau nhà vấp phải dây điện. Máy chủ tắt phụp.</p>

<p>Khi máy chủ khởi động lại, ổ cứng đang ở trạng thái nào?</p>
<p>Giới kiến trúc sư Database thời đó đối mặt với một tình thế tiến thoái lưỡng nan tàn khốc về việc "Khi nào nên ghi dữ liệu từ RAM xuống Ổ cứng":</p>
<ol>
<li><strong>Chính sách Force (Ép buộc ghi):</strong> Database phải ép ổ cứng ghi xong xuôi toàn bộ dữ liệu rồi mới dám báo cho User là "Chuyển tiền thành công". Rất an toàn! Nhưng vì ổ cứng phải Random I/O (nhảy cóc tìm chỗ ghi), tốc độ rớt thê thảm xuống còn 10 giao dịch/giây. Ngân hàng phá sản vì App quá lag.</li>
<li><strong>Chính sách No-Force (Ghi lười biếng):</strong> Database cứ để dữ liệu trên RAM cho chạy nhanh, báo "Thành công!" luôn cho User, rồi lúc nào rảnh mới ngầm ghi xuống ổ cứng. Rất nhanh! Nhưng cúp điện một phát là dữ liệu trên RAM bay sạch. Mất 1 tỷ của khách. Ngân hàng hầu tòa.</li>
</ol>

<p>Chưa kể, nếu Database tự động xả RAM xuống đĩa cứng <em>trong lúc</em> giao dịch còn chưa xong (gọi là chính sách <strong>Steal</strong>) mà cúp điện thì sao? Ổ cứng lúc này chứa một mớ rác rưởi nửa vời.</p>
<p>Ngành IT cần một phép màu: Một thuật toán cho phép dùng combo <strong>Steal / No-Force</strong> (tốc độ tối đa, linh hoạt tối đa) nhưng phải cam kết dữ liệu sống sót 100% khi cúp điện. C. Mohan đã giáng trần và mang đến ARIES.</p>

<h2>Đột Phá Học Thuật: Ghi Nhật Ký Trước (WAL) Và Số Thứ Tự LSN</h2>
<p>Cú đột phá của Mohan dựa trên hai trụ cột bất diệt: Write-Ahead Log (WAL) và Log Sequence Number (LSN).</p>

<p><strong>Luật thép của WAL</strong> vô cùng đơn giản nhưng không thể bị phá vỡ: <em>Mày phải ghi CÁI Ý ĐỊNH SỬA DỮ LIỆU vào cuốn Nhật ký (Log) trên đĩa cứng TRƯỚC KHI mày chạm vào Dữ liệu Thật trên đĩa cứng.</em></p>
<p>Cuốn Nhật ký (WAL) chỉ là một cái file Text. Việc viết chữ nối đuôi vào cuối file Text (Sequential I/O) trên ổ cứng diễn ra với tốc độ tên lửa. Vậy nên, khi chuyển 1 tỷ, Database chỉ mất 0.1 mili-giây để chép 1 dòng siêu nhẹ vào WAL: <code>[Trừ 1 Tỷ của TK A]</code>. Sau đó nó báo "Thành công!" cho User ngay tắp lự. Còn cái mớ dữ liệu Data thật sự nặng nề? Cứ vứt đó trên RAM (No-Force), bao giờ rảnh thì ổ cứng tự cập nhật sau.</p>

<p>Mỗi dòng nhật ký được gán một con số tăng dần gọi là <strong>LSN (Log Sequence Number)</strong>. Nếu máy chủ sập nguồn, cuốn WAL này trở thành chiếc "Hộp đen máy bay" bất khả xâm phạm, ghi lại chính xác dòng thời gian của mọi thứ đã xảy ra.</p>

<h2>Giải Phẫu Kiến Trúc: Vũ Điệu Phục Hồi 3 Bước (3-Pass Recovery)</h2>
<p>Ma thuật thực sự của ARIES nằm ở lúc máy chủ được cắm điện lại. Lúc này, Database đóng cửa, từ chối mọi kết nối của User. Nó bước vào chế độ "Crash Recovery Mode" (Cấp cứu) và múa một bài quyền 3 bước tuyệt đẹp trên cuốn WAL.</p>

<h3>Bước 1: Analysis (Khám Nghiệm Hiện Trường)</h3>
<p>ARIES đọc cuốn WAL từ điểm lưu an toàn gần nhất (Checkpoint) tiến về phía trước. Nó nhặt ra danh sách: Những giao dịch nào đang chạy dở dang lúc cúp điện (gọi là "Kẻ thua cuộc" - Losers), và những giao dịch nào đã ấn nút Commit thành công ("Người chiến thắng" - Winners). Nó cũng chốt được chính xác LSN nào bắt đầu gây hỏng ổ cứng.</p>

<h3>Bước 2: Redo (Lặp Lại Lịch Sử Mù Quáng)</h3>
<p>Đây là quyết định điên rồ và thiên tài nhất của C. Mohan: <strong>Lặp lại toàn bộ lịch sử, kể cả những sai lầm! (Repeating History)</strong>.</p>
<p>ARIES đọc WAL lại một lần nữa tiến về phía trước. Nó nhắm mắt làm lại <em>MỌI HÀNH ĐỘNG</em> có trong nhật ký, bất kể đó là của "Kẻ thắng" hay "Người thua". Nó cố tình ép Database quay trở về <em>đúng cái trạng thái rác rưởi, nát bét, hỗn độn nhất</em> ở phần ngàn giây ngay trước khi ông lao công rút phích cắm. Tại sao phải tự làm khổ mình? Vì việc tái tạo lại cấu trúc vật lý của ổ cứng (các trang B-Tree) giúp cho bước thứ 3 có thể vận hành bằng toán học một cách hoàn hảo, không có bug.</p>

<h3>Bước 3: Undo (Hối Lỗi Cứu Rỗi)</h3>
<p>Khi lịch sử đã được tái hiện, ARIES bắt đầu đọc WAL <em>ngược từ dưới lên trên (Backwards)</em>. Đối với mỗi "Kẻ thua cuộc" (Losers) tìm thấy ở Bước 1, ARIES nhìn vào lệnh log (ví dụ: <code>[Đã cộng 1 Tỷ cho TK B]</code>) và sinh ra một lệnh đảo ngược hoàn hảo (<code>[Trừ lại 1 Tỷ từ TK B]</code>). Khi quét ngược về đến tận cùng, toàn bộ các giao dịch chạy dở dang (của Kẻ thua) bị cắt gọt sạch sẽ khỏi ổ cứng một cách phẫu thuật.</p>

<p>Đỉnh cao của sự cẩn thận: Nhỡ đâu đang Undo lại bị... cúp điện lần 2 thì sao? ARIES cứ mỗi lần Undo xong 1 lệnh, nó lại ghi vào WAL một dòng "Nhật ký Bồi thường" (CLR). Nó đảm bảo hệ thống có tính <em>Idempotent (Lũy đẳng)</em>: Bạn có thể cúp điện 100 lần trong lúc đang cấp cứu, cứ có điện lại là ARIES tự động làm tiếp tục chỗ đang làm dở mà không hề làm sai dữ liệu.</p>

<h2>Thực Tiễn Production: Ổ Cứng SSD Và Sao Lưu Nhạc Trưởng (Replication)</h2>
<p>Suốt 30 năm, ARIES ngự trị như một vị thần. Nhưng phần cứng hiện đại đang rung chuyển ngai vàng của nó.</p>

<p>ARIES được thiết kế để hạn chế tối đa việc đọc Random trên ổ cứng HDD cơ học (mất 10ms/phát đọc). Nhưng ngày nay, ổ cứng SSD NVMe đọc Random chỉ mất 0.05ms. Nhờ phần cứng quá mạnh, giới khoa học đang đề xuất bỏ qua bước "Redo mù quáng", tạo ra các công nghệ như "Constant Time Recovery" (Phục hồi thời gian thực của SQL Server), cho phép User được kết nối vào Database <em>ngay trong lúc</em> hệ thống đang chạy Undo.</p>

<p>Bên cạnh đó, cuốn sổ WAL của ARIES đã tiến hóa thành một vũ khí đáng sợ. Ngày nay, WAL không chỉ dùng để chống sập nguồn. Nó dùng để <strong>Replication (Sao chép máy chủ)</strong>. Khi máy chủ Postgres Chính (Primary) ghi WAL, nó bắn luồng Text đó qua mạng LAN cho máy chủ Phụ (Replica). Máy chủ Phụ cứ thế bật chế độ "Redo của ARIES" và nhai đi nhai lại cái file WAL đó 24/7. Thế là ta có một con Server Phụ y hệt Server Chính đến từng Byte!</p>

<h2>Bình Luận Chuyên Gia & Di Sản (Expert Critique & Legacy)</h2>
<p>Bài báo ARIES của C. Mohan nổi tiếng là một cực hình đối với bất kỳ sinh viên IT nào (Nó dài 30 trang toàn chữ và chứng minh thuật toán chằng chịt). Nhưng nó là một kỳ quan của Khoa học Máy tính. Trước ARIES, khôi phục Database là một thứ ma thuật đen (Black Art) chứa đầy bug và dữ liệu bị hỏng. ARIES đã biến nó thành một bộ môn toán học nghiêm ngặt tuyệt đối.</p>

<p>Mỗi lần bạn quẹt thẻ tín dụng, bạn đang đặt trọn niềm tin vào thuật toán ARIES. Nó là tấm lưới bảo hiểm cuối cùng của thế giới kỹ thuật số. Nó là minh chứng hùng hồn rằng: Chỉ cần một cuốn Nhật ký tuần tự và một bộ quy tắc khắt khe, phần mềm có thể sống sót qua sự hỗn loạn và những tai nạn vật lý tàn khốc nhất của thế giới thực mà không bao giờ đánh rơi dù chỉ 1 Byte sự thật.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'ARIES Recovery: Ma Thuật Giữ Database Sống Sót Dù Rút Phích Cắm Đột Ngột',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['C. Mohan', 'ARIES', 'Crash Recovery', 'Write-Ahead Log', 'ACID']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> 1992年にIBMのC. Mohan（C・モハン）らが発表した「ARIES（アリエス）」は、データベースの「クラッシュリカバリ（障害復旧）」の歴史を変えた最高傑作のアルゴリズムです。データベースの絶対的なルールである「ACID特性」のうち、「D（耐久性：停電してもデータが消えない）」と「A（原子性：中途半端な状態で終わらない）」を数学的に保証する魔法の仕組みです。</li>
<li><strong>根本的な問題：</strong> もし、銀行システムで「Aさんから100万円引いた直後」にサーバーの電源が落ちたらどうなるでしょう？ ハードディスクには「引き出されたけど、振り込まれていない」という最悪の中途半端なデータ（破損データ）が残ります。ARIES以前は、これを防ぐために「処理のたびにディスクに強制書き込み」をしてシステムを劇遅にするか、クラッシュ後にDBを何時間も止めて手作業で直すしかありませんでした。</li>
<li><strong>解決策：</strong> ARIESは<strong>「Write-Ahead Logging（WAL：ログ先行書き込み）」</strong>のルールを完璧に定義しました。データベースは重い実データをハードディスクに書き込むのを後回しにし、まずは「Aさんから100万引くぞ」という一行の「日記（ログ）」だけをディスクに一瞬で書き込みます。もし停電でクラッシュしても、再起動時にこの日記を「解析（Analysis）」「歴史の再現（Redo）」「失敗の取り消し（Undo）」という3段階で読み直すことで、データベースを停電の1ミリ秒前の完璧な状態に復元します。</li>
<li><strong>現代の真実：</strong> 現在、PostgreSQL、SQL Server、IBM Db2、MySQL InnoDBなど、名だたるすべてのリレーショナルデータベースが、このARIESアルゴリズム（またはその派生系）で動いています。あなたの銀行口座が停電で消滅しないのは、この論文のおかげです。</li>
</ul>

<h2>歴史的背景：電源引き抜きという大惨事 ⚡</h2>
<p>ARIESの天才性を理解するために、最悪のシナリオを想像してください。あなたは銀行のIT管理者です。今まさに、システムが「口座Aから1億円を引き出し、口座Bへ振り込む」という巨大なトランザクションを処理しています。データベースがメモリ（RAM）上で口座Aから1億円をマイナスしたその瞬間、清掃員がモップでサーバーの電源コードを引っ掛けてしまいました。サーバーは沈黙します。</p>

<p>電源を入れ直したとき、ハードディスクは一体「どんな状態」になっているのでしょうか？</p>
<p>1970年代から80年代にかけて、データベースのアーキテクトたちは「いつ、メモリのデータをハードディスクに書き込むべきか？」という残酷なジレンマに苦しんでいました。</p>
<ol>
<li><strong>Force（強制書き込み）ポリシー：</strong> 「送金完了！」とユーザーに通知する前に、必ず実データをハードディスクに書き込むルール。絶対に安全ですが、ハードディスクのランダム書き込みは非常に遅いため、1秒間に10回しか処理できず、サービスが遅すぎて会社が潰れます。</li>
<li><strong>No-Force（怠惰な書き込み）ポリシー：</strong> ユーザーにはすぐに「完了！」と通知し、重いディスク書き込みは後で暇なときに裏側でやるルール。爆速ですが、裏側で書き込む前に停電したら「完了したはずの1億円」が消滅し、裁判になって会社が潰れます。</li>
</ol>

<p>さらに、データベースのメモリが一杯になったからといって、トランザクションの処理途中（送金中）に勝手にハードディスクにデータを書き出してしまう（<strong>Stealポリシー</strong>と呼びます）と、その瞬間に停電した場合、ディスクには「口座Aの残高だけが減った中途半端なゴミデータ」が永遠に残ってしまいます。</p>

<p>IT業界は、<strong>「Steal / No-Force（最高に速くて、最高にメモリを節約できる）」</strong>というルールを使いながらも、停電時には100%データを復元できる「奇跡のアルゴリズム」を求めていました。それに答えたのが、C. Mohanの「ARIES」です。</p>

<h2>学術的ブレイクスルー：Write-Ahead Log (WAL) と LSN 📝</h2>
<p>モハンのブレイクスルーは、2つの絶対的な柱によって支えられています。「Write-Ahead Log（WAL）」と「Log Sequence Number（LSN）」です。</p>

<p><strong>WALの鉄の掟</strong>は極めてシンプルです。<em>「ハードディスク上の『実際のデータ』を書き換える前に、必ず『これから何をするかという意図』をディスク上の『ログ（日記）』に書き込まなければならない」</em>。</p>

<p>この「ログファイル」はただのテキストファイルです。ファイルの末尾に文字を追記するだけ（シーケンシャルI/O）なので、ハードディスクでも信じられないほど爆速で書き込めます。だから送金処理が来たとき、データベースは一瞬で <code>[口座Aを -1億円する]</code> という短い日記をディスクに書き込みます。そして即座にユーザーに「成功！」と返します。実際の重いデータ更新はメモリ上に放置（No-Force）しておき、あとでゆっくりディスクに書き込みます。</p>

<p>日記の1行1行には、<strong>「LSN（ログ・シーケンス番号）」</strong>という、絶対に増え続ける通し番号が振られます。もしサーバーがクラッシュしても、このWALのファイルだけは無傷で残り、「過去に何が起きたか」を完璧に証明する「飛行機のブラックボックス」として機能するのです。</p>

<h2>アーキテクチャの徹底解剖：3段階の復元ダンス（3-Pass Recovery） 💃</h2>
<p>ARIESの真の魔法は、サーバーの電源が再び入った時に始まります。データベースはユーザーからの接続を拒否し、「クラッシュリカバリモード（緊急手術）」に入ります。そして、残されたWALの日記を使って、厳格な「3段階のアルゴリズム」を実行します。</p>

<h3>フェーズ1：Analysis（状況分析・被害状況の確認）</h3>
<p>ARIESは日記（WAL）を過去の安全な地点から「前（未来）」に向かって読み進めます。停電した瞬間に「コミット（確定）ボタンが押されていた処理（勝者）」と、「処理の途中で終わってしまった処理（敗者）」を正確にリストアップします。また、ハードディスクの破損がどのLSN番号から始まっているかを特定します。</p>

<h3>フェーズ2：Redo（歴史の愚直な繰り返し）</h3>
<p>ここが、モハンの最も常軌を逸した天才的な発想です。<strong>「歴史を、間違いごとすべて繰り返す（Repeating History）」</strong>のです。</p>
<p>ARIESは再び日記を「前」に向かって読みます。そして、それが「勝者」の処理であろうと「敗者」の処理であろうと、日記に書いてある行動を<em>すべてそのまま再実行</em>します。わざと、電源が抜かれる1ミリ秒前の「中途半端で、ゴミが散らかっている最悪の状態」に、ハードディスクの物理構造（B-Treeなど）を完璧に復元するのです。なぜそんなドMなことをするのでしょうか？ それは「物理状態を停電直前と完全に一致させることで、次のステップ（取り消し処理）を数学的にバグなく確実に実行できるようにするため」です。</p>

<h3>フェーズ3：Undo（敗者の取り消し・贖罪）</h3>
<p>歴史が再現された後、ARIESは今度は日記を<strong>「後ろ（過去）」に向かって逆再生</strong>で読み進めます。フェーズ1で見つけた「敗者（中途半端な処理）」の日記（例：<code>[口座Bに+1億円した]</code>）を見つけるたびに、それと「全く逆の行動（<code>[口座Bから-1億円する]</code>）」を機械的に実行していきます。日記を一番最初まで逆再生し終わった時、中途半端だった処理はすべて外科手術のように綺麗にディスクから消し去られ、データベースは「完璧に正しい状態」へと戻ります。</p>

<p>ARIESが異常に堅牢なのは、「このUndo（取り消し）の手術をしている最中に、もう一回停電したらどうする？」という最悪のケースまで想定している点です。ARIESはUndoを実行するたびに「取り消しましたよ」という新しい日記（CLR）を書きます（べき等性の保証）。これにより、復旧中に100回停電しようと、ARIESは何度でも蘇り、絶対にデータを壊すことなく最後まで復旧を成し遂げます。</p>

<h2>現代の真実：SSDの進化とレプリケーション（複製）への応用 🚀</h2>
<p>30年間、ARIESは絶対王者として君臨してきました。しかし、最新のハードウェアがその常識を揺るがしています。</p>

<p>ARIESの「歴史を繰り返す（Redo）」というフェーズは、遅いハードディスク（HDD）のランダム読み込みを減らすために最適化されたものでした。しかし現在のNVMe SSDはランダム読み込みがHDDの200倍も高速です。そのため、MicrosoftのSQL Server（Constant Time Recovery）など最新の研究では、ARIESのルールを一部曲げて「Redoをショートカットし、復旧中（Undo中）でもユーザーをデータベースに接続させる」という荒技が実装され始めています。</p>

<p>さらに、ARIESが発明した「WAL（日記）」は、現代ではクラッシュ復旧以外の巨大な役割を担っています。それが<strong>「レプリケーション（サーバーの複製）」</strong>です。メインのPostgreSQLサーバーがWALを書くと、そのテキストデータがLANケーブルを通ってサブのサーバーにリアルタイムで転送されます。サブサーバーは、ひたすらARIESの「Redo（歴史の繰り返し）」を実行し続けるだけで、メインサーバーと全く同じデータを保った完璧なコピー（レプリカ）になるのです。</p>

<h2>専門家による批評と、不滅のレガシー 🏛️</h2>
<p>C. MohanのARIES論文は、30ページに及ぶ難解な数式と証明で埋め尽くされており、IT学生を絶望させることで有名です。しかし、これはコンピュータサイエンスにおける「金字塔」です。ARIES以前のクラッシュリカバリは、職人の勘に頼った「黒魔術」であり、常にデータ破損のバグを抱えていました。ARIESはそれを「厳格で、数学的に証明可能な科学」へと昇華させたのです。</p>

<p>あなたがクレジットカードを切るとき、あなたはARIESのアルゴリズムに全財産を預けています。それはデジタル世界の「究極のセーフティネット」です。「シーケンシャルな日記」と「厳格な復元ルール」さえあれば、ソフトウェアは現実世界の無慈悲で予測不可能な停電や物理クラッシュを乗り越え、たった1バイトの真実も失うことなく蘇ることができる。ARIESはそれを証明した人類の遺産なのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'ARIESアルゴリズム：突然の停電からデータベースを救う「魔法の復元技術」',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['C. Mohan', 'ARIES', 'Crash Recovery', 'Write-Ahead Log', 'ACID']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 14 (ARIES Recovery)!\n";
