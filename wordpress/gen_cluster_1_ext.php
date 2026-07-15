<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'lru_buffer_pool.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'LRU Buffer Pool',
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

$cat_en = setup_term('Database Architecture', 'category', 'en');
$cat_vi = setup_term('Kiến Trúc Database', 'category', 'vi');
$cat_ja = setup_term('データベースアーキテクチャ', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>AI Summary / Executive Abstract</h2>
<ul>
<li><strong>What is it?</strong> The LRU (Least Recently Used) Buffer Pool is a foundational memory management architecture used in relational databases to bridge the extreme speed gap between volatile RAM (fast) and persistent disk storage (slow).</li>
<li><strong>The Core Problem:</strong> Accessing a hard drive takes milliseconds ($10^{-3}$ seconds), whereas accessing RAM takes nanoseconds ($10^{-9}$ seconds). If a database read directly from the disk for every query, the system would collapse under I/O wait times.</li>
<li><strong>The Solution:</strong> The database reserves a large chunk of RAM (the Buffer Pool) to cache data pages. When RAM is full, the LRU algorithm evicts the page that has not been accessed for the longest time, assuming that recently used data will likely be used again.</li>
<li><strong>Modern Reality:</strong> Pure LRU is no longer used because it is vulnerable to "Sequential Flooding" (where a full table scan flushes the entire cache). Modern databases like PostgreSQL and MySQL use variants like Clock-Sweep or LRU-K to prevent this.</li>
</ul>

<h2>Historical Context & The Catalyst: The Impending I/O Wall</h2>
<p>To understand the sheer necessity of the Buffer Pool, we must teleport back to the 1970s and 1980s. The hardware landscape was drastically different. The CPU was the brain, operating at speeds that were accelerating exponentially according to Moore\'s Law. Memory (RAM) was incredibly expensive but fast. And then, at the bottom of the hierarchy, was the magnetic hard disk drive (HDD)—a mechanical device consisting of spinning platters and moving metallic arms.</p>

<p>The fundamental laws of physics dictate that moving a physical, mechanical arm across a spinning disk will always be millions of times slower than sending an electrical signal through a silicon chip. This created what computer scientists called the "I/O Wall." You could have the fastest processor in the world, but if it had to wait for the disk to spin, it was effectively paralyzed. A database system that interacted directly with the disk for every <code>SELECT</code> or <code>UPDATE</code> statement was fundamentally doomed to fail at scale.</p>

<p>The solution was to create an intermediary layer—a staging area in the fast, volatile RAM. This became known as the Buffer Pool. But because RAM is finite (and was extremely small in the 80s), you couldn\'t fit the entire database into it. You had to selectively choose which blocks of data (Pages) to keep in RAM and which to leave on disk. This birthed the ultimate computer science dilemma: Cache Replacement Policy.</p>

<h2>The Academic Breakthrough: The Principle of Locality</h2>
<p>The academic foundation of the LRU algorithm rests upon a phenomenon known as the <em>Principle of Locality</em>. Researchers observed that data access in computer programs is not uniformly random. It follows distinct patterns:</p>
<ul>
<li><strong>Temporal Locality:</strong> If a piece of data is accessed, it is highly likely to be accessed again in the near future. (e.g., A user logs in and immediately requests their profile data, then their settings, both located on the same page).</li>
<li><strong>Spatial Locality:</strong> If a piece of data is accessed, data located physically close to it is likely to be accessed next. (e.g., Reading a sequential list of orders).</li>
</ul>

<p>The Least Recently Used (LRU) algorithm was designed to perfectly exploit Temporal Locality. Its logic is beautifully simple: The future will look like the recent past. The data page that has gone the longest without being touched is the "coldest" and is the safest candidate to be evicted (thrown out of RAM) when space is needed for a new page.</p>

<h2>Deep Architectural Walkthrough: How LRU Actually Works</h2>
<p>Let\'s open up the hood and look at the actual data structures required to build an LRU Buffer Pool. You cannot simply scan the entire RAM to find the oldest page—that would require an $O(N)$ scan, which would destroy performance. An LRU cache must operate in $O(1)$ constant time for both fetching data and evicting data.</p>

<p>To achieve this $O(1)$ magic, the architecture requires two interconnected data structures:</p>

<h3>1. The Hash Map (The Directory)</h3>
<p>When the database needs Page #42, it needs to know instantly if Page #42 is already in RAM. It uses a Hash Map where the Key is the Page ID, and the Value is a memory pointer to where the data physically resides in the Buffer Pool. Hash Maps provide $O(1)$ lookups.</p>

<h3>2. The Doubly-Linked List (The Timeline)</h3>
<p>To track the "age" or "recency" of pages, the system uses a Doubly-Linked List. Imagine a chain of nodes. The "Head" of the chain represents the Most Recently Used (MRU) page. The "Tail" of the chain represents the Least Recently Used (LRU) page.</p>

<p>Here is the exact lifecycle of a page read:</p>
<ol>
<li><strong>Cache Miss:</strong> The query asks for Page #99. The Hash Map returns null. The database must go to the physical disk (10ms penalty). It reads the 8KB block into a free slot in the Buffer Pool.</li>
<li><strong>Insertion:</strong> The database adds Page #99 to the Hash Map. It then creates a node for Page #99 and places it at the very Head (MRU end) of the Doubly-Linked List.</li>
<li><strong>Cache Hit & Promotion:</strong> Five milliseconds later, another query asks for Page #99. The Hash Map instantly finds it. But crucially, the Linked List must be updated. The system unlinks Page #99 from its current position in the list and moves it back to the absolute Head. This "Promotion" is what keeps frequently accessed data alive.</li>
<li><strong>Eviction:</strong> The RAM is completely full. A new query requests Page #100. The system looks at the Tail of the Linked List. Whatever page is sitting at the Tail is the coldest data. The system evicts it, removes it from the Hash Map, and loads Page #100 into its physical memory slot, placing Page #100 at the Head.</li>
</ol>

<h2>Modern Production Reality: The Sequential Flooding Catastrophe</h2>
<p>Pure LRU is elegant and conceptually sound. But if you deploy pure LRU in a modern production database, it will fail catastrophically under specific workloads. This failure mode is known as <strong>Sequential Flooding</strong>.</p>

<p>Imagine your Buffer Pool holds 10,000 pages. They are full of hot, frequently accessed user profiles. Now, an analyst runs a massive, poorly-optimized query: <code>SELECT SUM(salary) FROM massive_history_table</code>. This query requires performing a Full Table Scan on a table containing 100,000 pages.</p>

<p>Under pure LRU, the database reads Page 1 of the history table, puts it at the Head, and evicts a hot user profile from the Tail. It then reads Page 2, Page 3, all the way to Page 100,000. Because the query touches 100,000 pages, the entire Buffer Pool is completely flushed and overwritten by this single query. The 10,000 hot user profiles are gone. The cache is now filled with historical data that the analyst will never read again.</p>

<p>Suddenly, the live application grinds to a halt because every single user request is now causing a Cache Miss. The cache was "flooded".</p>

<h3>The Fix: LRU-K and Clock-Sweep</h3>
<p>To solve this, production databases had to evolve:</p>
<ul>
<li><strong>LRU-K (MySQL InnoDB):</strong> Instead of promoting a page to the MRU position after a <em>single</em> touch, LRU-K requires a page to be touched $K$ times (often $K=2$) before it is considered "hot". A full table scan only touches each page once, so they never get promoted to the hot end of the list and are quickly evicted, saving the real hot data. MySQL implements a variation of this by dividing the LRU list into a "Young" sublist and an "Old" sublist.</li>
<li><strong>Clock-Sweep Algorithm (PostgreSQL):</strong> Maintaining a Doubly-Linked list requires heavy locking (mutexes) in a multi-threaded environment. PostgreSQL uses a lock-free approximation of LRU called the Clock Algorithm. Pages are arranged in a circular array with a "Usage Count". A "clock hand" sweeps around the circle. If it points to a page with a usage count > 0, it decrements the count and moves on. If it points to a page with count 0, that page is evicted. This perfectly mimics LRU but with drastically lower CPU overhead.</li>
</ul>

<h2>Expert Critique & Legacy</h2>
<p>The legacy of the LRU Buffer Pool is monumental. It taught the software engineering world that you cannot treat all hardware as a uniform black box. You must actively architect your software to bridge the impedance mismatch between silicon speed and mechanical speed.</p>

<p>However, as we move into the era of NVMe SSDs (where disk speeds are measured in microseconds) and massive RAM capacities (measured in Terabytes), the absolute necessity of complex LRU tracking is being debated. Some modern in-memory databases bypass the buffer pool entirely, relying directly on the OS virtual memory. Yet, for the vast majority of mission-critical relational databases, understanding the precise mechanics of the Buffer Pool remains the definitive difference between a junior developer and a senior database architect. It is the beating heart of database performance.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'The LRU Buffer Pool: Bridging the Gap Between Silicon and Iron',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['Buffer Pool', 'LRU', 'Memory Management', 'Database Architecture']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>Khái niệm cốt lõi:</strong> LRU (Least Recently Used) Buffer Pool là kiến trúc quản lý bộ nhớ đệm nền tảng trong các hệ quản trị CSDL quan hệ. Nó đóng vai trò làm cầu nối giảm xóc giữa tốc độ cực nhanh của RAM và tốc độ rùa bò của ổ cứng vật lý.</li>
<li><strong>Vấn đề giải quyết:</strong> Đọc dữ liệu từ ổ cứng tốn hàng mili-giây, trong khi đọc từ RAM chỉ tốn nano-giây (nhanh hơn hàng triệu lần). Nếu Database chạm vào đĩa cứng cho mọi câu query, hệ thống sẽ chết chìm trong I/O Wait.</li>
<li><strong>Cơ chế hoạt động:</strong> Database xí trước một cục RAM khổng lồ (Buffer Pool) để chứa các trang dữ liệu (Pages). Khi RAM đầy, thuật toán LRU sẽ thẳng tay "đá" trang dữ liệu nào <em>lâu nhất chưa được ai đụng tới</em> ra khỏi RAM, nhường chỗ cho dữ liệu mới.</li>
<li><strong>Thực tiễn Production:</strong> Thuật toán LRU thuần túy đã chết yểu vì dính lỗi "Sequential Flooding" (Bị ngập lụt do quét toàn bảng). Các hệ thống hiện đại như PostgreSQL và MySQL phải dùng các biến thể phức tạp hơn như Clock-Sweep hoặc LRU-K để sinh tồn.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Bức Tường I/O (The I/O Wall) Đáng Sợ</h2>
<p>Để thực sự thấu hiểu sự vĩ đại của Buffer Pool, bạn không thể nhìn nó bằng lăng kính của những chiếc ổ cứng SSD NVMe tốc độ 7000MB/s hiện tại. Hãy quay ngược cỗ máy thời gian về thập niên 80. Khi đó, trái tim của máy tính là CPU đang phát triển với tốc độ hàm mũ theo định luật Moore. RAM thì đắt như vàng. Và nằm dưới đáy của chuỗi thức ăn phần cứng là Ổ đĩa từ tính (HDD) - một cỗ máy cơ học cục mịch với những đĩa kim loại quay xè xè và cánh tay đòn di chuyển lộc cộc.</p>

<p>Vật lý cơ bản chỉ ra rằng: Việc di chuyển một cánh tay kim loại có khối lượng đi qua một mặt đĩa sẽ LUÔN LUÔN chậm hơn hàng triệu lần so với việc bắn một electron chạy qua bảng mạch điện tử. Giới khoa học máy tính gọi đây là "Bức tường I/O" (The I/O Wall). Bạn có thể mua con chip mạnh nhất thế giới, nhưng nếu nó phải đứng đợi cái ổ cứng quay mặt đĩa để lấy dữ liệu, thì con chip đó cũng chỉ là một cục phế liệu. Một cái Database mà cứ hễ có lệnh <code>SELECT</code> là chui xuống ổ cứng để đọc thì chắc chắn sẽ sập ngay khi có 10 user truy cập cùng lúc.</p>

<p>Giải pháp duy nhất là tạo ra một vùng đệm (Staging area) nằm ngay trong bộ nhớ RAM siêu tốc. Vùng đệm này được gọi là <strong>Buffer Pool</strong>. Tuy nhiên, bi kịch ở chỗ RAM thì có hạn (thời đó chỉ vài Megabyte), còn Database thì nặng hàng Gigabyte. Bạn không thể nhét con voi vào tủ lạnh. Bạn bắt buộc phải chọn lọc: Giữ lại block dữ liệu nào (Page) trên RAM, và vứt block nào xuống lại ổ cứng? Bài toán định mệnh mang tên "Cache Replacement Policy" (Chính sách thay thế bộ nhớ đệm) chính thức ra đời.</p>

<h2>Đột Phá Học Thuật: Nguyên Lý Cục Bộ (Principle of Locality)</h2>
<p>Sự khai sinh của thuật toán LRU không đến từ sự ngẫu hứng, mà dựa trên một quan sát học thuật mang tính nền tảng gọi là <em>Nguyên lý Cục bộ (Principle of Locality)</em>. Các kỹ sư nhận ra rằng, phần mềm máy tính không hề truy cập dữ liệu một cách ngẫu nhiên loạn xạ. Chúng tuân theo 2 quy luật khắc nghiệt:</p>
<ul>
<li><strong>Cục bộ Không gian (Spatial Locality):</strong> Nếu bạn vừa đọc dòng số 1, thì xác suất rất cao bạn sẽ đọc tiếp dòng số 2 nằm ngay cạnh nó.</li>
<li><strong>Cục bộ Thời gian (Temporal Locality):</strong> Nếu bạn vừa đọc Profile của một User, thì xác suất cực cao là chỉ vài phần ngàn giây sau, bạn sẽ cần truy cập lại chính Profile đó (ví dụ để lấy Avatar, email).</li>
</ul>

<p>Thuật toán <strong>Least Recently Used (LRU)</strong> được sinh ra để vắt kiệt sức mạnh của quy luật Cục bộ Thời gian. Logic của nó là một sự suy diễn tuyệt đẹp: <em>"Tương lai là tấm gương phản chiếu của quá khứ gần"</em>. Cái Page dữ liệu nào mà đã bị mốc meo lâu nhất không ai thèm ngó tới, thì nó chính là cục dữ liệu "lạnh lẽo" nhất, và xứng đáng bị đuổi cổ khỏi RAM để nhường chỗ cho Page mới.</p>

<h2>Giải Phẫu Kiến Trúc: Bóc Tách Động Cơ LRU Cấp Độ Data Structure</h2>
<p>Bây giờ, hãy xắn tay áo lên và nhìn sâu vào cấp độ Code. Bạn không thể thiết kế một vòng lặp <code>for</code> chạy từ đầu đến cuối RAM để tìm ra cái Page cũ nhất. Nếu Buffer Pool của bạn có 1 triệu Page, vòng lặp $O(N)$ đó sẽ làm CPU bốc cháy. Một hệ thống LRU chuẩn mực bắt buộc phải vận hành ở tốc độ $O(1)$ (Constant time) cho cả thao tác Tìm kiếm (Fetch) và thao tác Đuổi cổ (Eviction).</p>

<p>Để đạt được ma thuật $O(1)$ này, kiến trúc sư hệ thống phải ghép nối hai cấu trúc dữ liệu kinh điển lại với nhau:</p>

<h3>1. Hash Map (Cuốn Danh Bạ)</h3>
<p>Khi Database cần tìm Page số 42, nó không rảnh để đi mò mẫm. Nó lôi cuốn danh bạ Hash Map ra. Key là <code>Page_ID = 42</code>, Value là con trỏ (Pointer) trỏ thẳng vào địa chỉ vật lý của bộ nhớ RAM đang chứa Page đó. Hash Map cho tốc độ tra cứu $O(1)$ ngay lập tức.</p>

<h3>2. Doubly-Linked List (Dòng Thời Gian)</h3>
<p>Để biết Page nào già, Page nào trẻ, hệ thống dùng một Danh sách liên kết đôi. Đầu danh sách (Head) là ngai vàng dành cho kẻ vừa được <strong>Sử dụng Gần nhất (MRU - Most Recently Used)</strong>. Cuối danh sách (Tail) là lãnh địa của kẻ <strong>Bị lãng quên lâu nhất (LRU - Least Recently Used)</strong>.</p>

<p>Vòng đời của một Page diễn ra khốc liệt như sau:</p>
<ol>
<li><strong>Cache Miss (Đọc hụt):</strong> Query đòi Page 99. Hash Map báo "Không có". DB phải chui xuống ổ đĩa cứng cạp đất (mất 10ms), lôi khối dữ liệu 8KB đó lên nhét vào RAM.</li>
<li><strong>Insertion (Chèn vào):</strong> DB ghi danh Page 99 vào Hash Map, rồi đặt nó ngồi chễm chệ ngay tại đỉnh (Head) của chuỗi Linked List. Nó là kẻ quyền lực nhất lúc này.</li>
<li><strong>Promotion (Thăng hạng):</strong> 5 giây sau, một query khác lại cần Page 99. DB tìm thấy ngay lập tức nhờ Hash Map. Và để thưởng công, DB giật đứt liên kết của Page 99 khỏi vị trí hiện tại, lôi cổ nó ném ngược lại lên đỉnh Head. Thao tác "Thăng hạng" này chính là bí quyết giữ cho dữ liệu "Nóng" (Hot data) không bao giờ bị đuổi khỏi RAM.</li>
<li><strong>Eviction (Hành quyết):</strong> Đột nhiên RAM đầy ứ ự. Một query mới mang Page 100 từ ổ đĩa lên và cần chỗ chứa. Hệ thống không cần suy nghĩ, nó nhìn thẳng xuống Đáy (Tail) của Linked List, túm lấy cái Page đáng thương đang nằm đó, chặt đầu (xóa khỏi Hash Map), và ghi đè nội dung của Page 100 lên vùng nhớ vật lý đó.</li>
</ol>

<h2>Thực Tiễn Production: Thảm Họa Ngập Lụt (Sequential Flooding)</h2>
<p>Trên giấy tờ, LRU là một kiệt tác. Nhưng nếu bạn vác bộ code LRU thuần túy này nhét vào MySQL đem ra chạy cho sàn Thương mại điện tử, hệ thống của bạn sẽ sập ngay trong đêm Big Sale. Tại sao? Vì một hiện tượng gọi là <strong>Sequential Flooding (Ngập lụt do quét tuần tự)</strong>.</p>

<p>Giả sử Buffer Pool của bạn đang chứa 10.000 Page toàn là dữ liệu giỏ hàng, user profile cực "Nóng", được truy cập liên tục. Đột nhiên, một gã Data Analyst ngớ ngẩn chạy một câu query báo cáo: <code>SELECT SUM(doanh_thu) FROM bang_lich_su_giao_dich_10_nam</code>. Câu query này ác nghiệt ở chỗ nó yêu cầu quét toàn bộ cái bảng (Full Table Scan) gồm 100.000 Page.</p>

<p>Thuật toán LRU ngoan ngoãn đọc Page 1 của bảng lịch sử, đẩy lên Head, và đuổi cổ một giỏ hàng nóng hổi ở Tail ra. Đọc Page 2, đuổi giỏ hàng thứ 2. Cứ thế lặp lại 100.000 lần. Hệ quả? Toàn bộ 10.000 Page rực lửa của hệ thống E-commerce bị cuốn trôi sạch sẽ xuống cống. RAM bây giờ chứa toàn những dữ liệu rác rưởi của quá khứ mà chả ai thèm đọc lại lần 2. Mọi user đang mua hàng đột ngột bị Cache Miss, CPU vọt lên 100%, Disk I/O quá tải, và hệ thống sập.</p>

<h3>Đẳng Cấp Của Các Ông Lớn (LRU-K & Clock-Sweep)</h3>
<p>Để sống sót, các Database buộc phải tiến hóa hệ gen của chúng:</p>
<ul>
<li><strong>LRU-K (MySQL InnoDB):</strong> Thay vì đưa một Page lên đỉnh Head chỉ sau 1 lần chạm, thuật toán LRU-K bắt buộc một Page phải được chạm <strong>K lần</strong> (thường K=2) thì mới được công nhận là "Hot". Câu query Full Scan của gã Analyst chỉ chạm mỗi Page đúng 1 lần, nên đống dữ liệu rác đó không bao giờ ngoi lên được khu vực Hot, và bị vứt đi ngay lập tức. Cứu được toàn bộ giỏ hàng của User.</li>
<li><strong>Thuật toán Clock (PostgreSQL):</strong> Việc duy trì Linked List đòi hỏi phải khóa (Lock/Mutex) liên tục, gây chậm khi có hàng ngàn thread. PostgreSQL đã thông minh vứt luôn cái Linked List, chuyển sang dùng mảng xoay vòng (Circular Array) với thuật toán Clock-Sweep. Cây kim đồng hồ quay liên tục quanh RAM, nếu gặp Page có cờ "Đã dùng", nó gỡ cờ đi rồi bỏ qua. Nếu gặp Page không có cờ, nó đuổi cổ. Thuật toán này mô phỏng hoàn hảo tính chất của LRU nhưng không hề cần tới Lock (Lock-free), đem lại hiệu năng CPU vô đối.</li>
</ul>

<h2>Bình Luận Chuyên Gia & Di Sản (Expert Critique & Legacy)</h2>
<p>Dù có vô vàn nhược điểm sơ khai, kiến trúc Buffer Pool LRU đã trở thành cột mốc vĩ đại nhất của ngành Software Engineering. Nó để lại một di sản giáo dục khắc nghiệt: <em>Bạn không thể thiết kế phần mềm mà không hiểu về phần cứng.</em> Việc chắp vá sự chênh lệch tốc độ giữa bảng mạch Silicon và mô-tơ cơ học là nghệ thuật tối cao của tối ưu hóa.</p>

<p>Ngày nay, với sự thống trị của ổ SSD NVMe (tốc độ đo bằng micro-giây) và những máy chủ có 2TB RAM, liệu Buffer Pool phức tạp có còn cần thiết? Một số kiến trúc sư đang tranh cãi việc vứt bỏ Buffer Pool và giao phó toàn bộ cho <code>mmap</code> (Virtual Memory của OS). Nhưng dù tương lai có ra sao, việc nắm vững cấu trúc Linked List + Hash Map và thấu hiểu bài toán Cache Eviction vẫn là ranh giới bất khả xâm phạm phân định giữa một Coder tay ngang và một System Architect đẳng cấp thế giới.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Cỗ Máy Hút Dữ Liệu: Mổ Xẻ LRU Buffer Pool Từ Thuật Toán Đến Production',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['Buffer Pool', 'LRU', 'Memory Management', 'Database Architecture']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> LRU（Least Recently Used）バッファプールは、リレーショナルデータベースにおける「メモリ管理の心臓部」です。爆速のRAM（メモリ）と、激遅のハードディスクの間の絶望的な速度差を埋めるための架け橋として機能します。</li>
<li><strong>根本的な問題：</strong> ディスクからデータを読むのは「ミリ秒（1000分の1秒）」かかりますが、RAMから読むのは「ナノ秒（10億分の1秒）」で済みます。もしデータベースが毎回律儀にディスクへ読みに行っていたら、システムは待ち時間で死んでしまいます。</li>
<li><strong>解決策：</strong> データベースは起動時に巨大なRAMの領域（バッファプール）を確保し、そこにデータをキャッシュします。RAMが満杯になったら、LRUアルゴリズムが「一番長い間、誰にも使われなかったデータ（一番古いデータ）」を容赦なくRAMから追い出し、新しいデータのための場所を空けます。</li>
<li><strong>現代の真実：</strong> 実は、純粋なLRUは現代の商用データベースでは使われていません。「全表スキャン（Full Table Scan）」という重いクエリが走ると、キャッシュ全体がゴミデータで上書きされてしまう「シーケンシャル・フラッディング（キャッシュの洪水）」という致命的な弱点があるためです。代わりに、MySQLはLRU-K、PostgreSQLはClockアルゴリズムという進化版を使っています。</li>
</ul>

<h2>歴史的背景：絶望の「I/Oの壁」 🧱</h2>
<p>バッファプールの絶対的な必要性を理解するためには、1980年代にタイムスリップしなければなりません。当時のコンピュータの世界は今とは全く異なっていました。CPUはムーアの法則に従ってとんでもない速度で進化していましたが、記憶装置の底辺にいる「ハードディスク（HDD）」は、物理的にモーターで円盤を回し、金属の腕（アーム）を動かすという「超・アナログな機械」でした。</p>

<p>物理法則の壁は残酷です。「物理的な金属の腕を動かす」ことは、「シリコンチップの中に電子信号を走らせる」ことに比べて、どう頑張っても数百万倍遅いのです。コンピュータサイエンスの学者たちは、これを<strong>「I/Oの壁（The I/O Wall）」</strong>と呼びました。どんなに世界一速いCPUを積んでいても、データを取り出すためにディスクの円盤が回るのを待たなければならないなら、そのCPUはただの置物です。「<code>SELECT</code>」文が来るたびにディスクに読みに行くデータベースは、ユーザーが数人増えただけで完全にフリーズしてしまう運命にありました。</p>

<p>この絶望的な壁を乗り越えるための唯一の解決策が、「高速なRAMの中に、一時的なデータの置き場（ステージングエリア）を作る」ことでした。これが<strong>バッファプール（Buffer Pool）</strong>の誕生です。しかし、80年代のRAMは容量が極端に小さく、データベース全体をRAMに入れることなど不可能でした。「どのデータをRAMに残し、どのデータをディスクに捨てるべきか？」という、コンピュータサイエンス史上最大のパズル「キャッシュ置換アルゴリズム」の戦いが幕を開けたのです。</p>

<h2>学術的ブレイクスルー：「局所性の原理」の発見 🔍</h2>
<p>LRUアルゴリズムは、単なる思いつきではありません。それは<strong>「局所性の原理（Principle of Locality）」</strong>という、プログラムの振る舞いに関する美しい学術的な発見に基づいて作られました。学者たちは、データへのアクセスが完全にランダムではなく、特定の偏りを持っていることに気づいたのです。</p>
<ul>
<li><strong>空間的局所性（Spatial Locality）：</strong> 1行目のデータを読んだら、次は高い確率でそのすぐ隣の2行目を読むだろう、という法則。</li>
<li><strong>時間的局所性（Temporal Locality）：</strong> 今さっきアクセスしたユーザーのプロフィールデータは、数ミリ秒後にまたすぐにアクセスされる確率が極めて高い、という法則。</li>
</ul>

<p><strong>Least Recently Used（LRU）</strong>アルゴリズムは、この「時間的局所性」を完璧にハックするために設計されました。その哲学は非常にシンプルです。「一番最近使われたものは、未来でも使われる。逆に、一番長い間ほったらかしにされているデータは、もう未来でも使われないだろうから、一番最初にRAMから追い出して（Evictして）しまえ！」というものです。</p>

<h2>アーキテクチャの徹底解剖：LRUは裏側でどう動くのか？ ⚙️</h2>
<p>では、エンジニアの視点でコードの裏側を覗いてみましょう。「一番古いデータを探す」ために、RAM全体を先頭から最後まで <code>for</code> ループで探すわけにはいきません。そんなことをしたらCPUが燃えてしまいます。LRUキャッシュは、データを取ってくるのも、古いデータを追い出すのも、すべて <strong>$O(1)$（定数時間：データが何億個あっても一瞬で終わるスピード）</strong> で実行できなければなりません。</p>

<p>この $O(1)$ の魔法を実現するために、LRUは2つのデータ構造を巧みに組み合わせています。</p>

<h3>1. ハッシュマップ（巨大な電話帳） 📖</h3>
<p>データベースが「ページ番号99」を欲しいとき、RAMの中を歩き回って探すことはしません。代わりに「ハッシュマップ」という電話帳を引きます。キー（Key）にページ番号99を入れると、値（Value）として「RAMのこの物理アドレスにデータがあるよ」というポインタが即座に返ってきます。これで検索は $O(1)$ です。</p>

<h3>2. 双方向連結リスト（時間のタイムライン） ⛓️</h3>
<p>データの「新しさ」を管理するために、データ同士を鎖で繋いだリスト（Doubly-Linked List）を使います。リストの「先頭（Head）」は、今まさに使われたばかりの<strong>「最も新しいデータ（MRU）」</strong>の玉座です。リストの「末尾（Tail）」は、誰からも忘れ去られた<strong>「最も古いデータ（LRU）」</strong>の墓場です。</p>

<p>データのライフサイクルは、このように過酷です：</p>
<ol>
<li><strong>キャッシュミス：</strong> ユーザーがページ99を要求。ハッシュマップにはありません。仕方なく遅いディスクまで取りに行き（10msの罰ゲーム）、RAMの空き枠にデータを入れます。</li>
<li><strong>リストへの挿入：</strong> ページ99をハッシュマップに登録し、タイムラインの「一番先頭（Head）」にドヤ顔で座らせます。</li>
<li><strong>キャッシュヒットと昇格（Promotion）：</strong> 5秒後、別のユーザーがまたページ99を要求します。ハッシュマップですぐに見つかります！ そしてここが重要です。システムはページ99を今の場所から引き剥がし、再び「一番先頭（Head）」へと大出世させます。使われるたびに先頭に戻るため、人気のあるデータは永遠にRAMから追い出されません。</li>
<li><strong>死の追放（Eviction）：</strong> 突然、RAMが100%満杯になりました。新しいデータをディスクから持ってくる場所がありません。システムは迷うことなくタイムラインの「一番末尾（Tail）」を見ます。そこに座っている哀れな古いデータをハッシュマップから抹消し、そのRAMの物理的なスペースに、新しいデータを上書きしてしまうのです。</li>
</ol>

<h2>現代の絶望的な弱点：「シーケンシャル・フラッディング（キャッシュ洪水）」 🌊</h2>
<p>LRUのメカニズムは美しいですが、現代の巨大なデータベースで純粋なLRUをそのまま使うと、ある日突然システムが大爆発します。それが<strong>「シーケンシャル・フラッディング（キャッシュの洪水）」</strong>と呼ばれる現象です。</p>

<p>あなたのバッファプールには、アクセス頻度の高い「ホットなユーザーデータ」が1万件分、大切に保管されているとします。そこに突然、空気を読まないデータアナリストが <code>SELECT SUM(売上) FROM 過去10年分の履歴テーブル</code> という、10万ページを読み込む「全表スキャン」クエリを実行しました。</p>

<p>純粋なLRUは愚直にルールに従います。履歴の1ページ目を読み込んで先頭に置き、末尾にいた大切なユーザーデータを1つ捨てます。2ページ目を読み込み、またユーザーデータを捨てます...。これを10万回繰り返すとどうなるでしょうか？ そうです、RAMの中にあった1万件のホットなデータは「完全に洪水に押し流されて消滅」してしまいます！ 代わりにRAMに残るのは、アナリストが二度と読まない過去のゴミデータだけです。この瞬間から、すべてのWebアクセスが遅いディスクへの読み込みを発生させ、サービスは完全にダウンします。</p>

<h3>天才たちの解決策：LRU-K と Clock-Sweep 🧠</h3>
<p>この大災害を防ぐため、商用データベースは独自の進化を遂げました。</p>
<ul>
<li><strong>LRU-K（MySQL InnoDBなど）：</strong> 1回触られただけでデータを先頭（ホット領域）に持っていくのをやめました。「K回（通常は2回）」連続でアクセスされて初めて、そのデータを「ホット」だと認めるのです。全表スキャンはデータを1回しか触らないため、ゴミデータはホット領域に入る前に即座に捨てられ、大切なユーザーデータは洪水を生き残ります。</li>
<li><strong>Clock-Sweepアルゴリズム（PostgreSQL）：</strong> リスト（鎖）を繋ぎ変える処理は、複数の処理が同時に走る環境では「ロック（待ち時間）」を発生させてしまいます。そこでPostgreSQLは鎖を捨て、データを円陣に並べました。時計の針（ポインタ）がぐるぐると回りながら、「使われたフラグ」が立っているデータはフラグを下ろして見逃し、フラグが立っていないデータを見つけたら容赦無く追い出す、という魔法のような軽量アルゴリズム（Lock-free）を発明しました。</li>
</ul>

<h2>専門家による批評と、LRUが遺したレガシー 🏛️</h2>
<p>LRUバッファプールのアーキテクチャは、ソフトウェアエンジニアリングにおける金字塔です。それは私たちに<strong>「ハードウェアの物理的な限界（ディスクの遅さ）を、ソフトウェアのアルゴリズム（RAMの賢い使い方）でねじ伏せることができる」</strong>ということを証明しました。</p>

<p>現代では、SSD（NVMe）が数マイクロ秒という異次元の速度で動くようになり、サーバーのRAMも数テラバイト積めるようになりました。「もう複雑なバッファプールなんていらないのでは？ OSの仮想メモリ機能（mmap）に全部任せればいい！」と主張する新しいアーキテクトたちも現れています。しかし、それでもなお、この <code>HashMap + Doubly-Linked List</code> の構造と「キャッシュの追い出し戦略」を深く理解しているかどうかは、単なるプログラマーと「数百万アクセスの負荷に耐えるシステムを作れる真のアーキテクト」を分ける、決定的な境界線であり続けているのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'シリコンと鉄の境界線：LRUバッファプールの深淵なるアーキテクチャ',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['Buffer Pool', 'LRU', 'Memory Management', 'Database Architecture']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 1 (LRU Buffer Pool)!\n";
