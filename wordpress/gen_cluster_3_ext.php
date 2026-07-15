<?php
require_once('wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// 1. Process Image
$image_path = ABSPATH . 'schema_ast_editor.jpg';
$upload_dir = wp_upload_dir();
$filename = basename($image_path);
$destination = $upload_dir['path'] . '/' . $filename;
if (file_exists($image_path)) {
    copy($image_path, $destination);
    $filetype = wp_check_filetype($filename, null);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => 'Schema AST Editor',
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

$cat_en = setup_term('Compilers & Tooling', 'category', 'en');
$cat_vi = setup_term('Trình Biên Dịch & Công Cụ', 'category', 'vi');
$cat_ja = setup_term('コンパイラとツーリング', 'category', 'ja');

pll_save_term_translations([
    'en' => $cat_en,
    'vi' => $cat_vi,
    'ja' => $cat_ja
]);

// 3. Content EN (Dan Abramov Persona)
$content_en = '
<h2>AI Summary / Executive Abstract</h2>
<ul>
<li><strong>What is it?</strong> An Abstract Syntax Tree (AST) is a tree-like data structure that represents the logical syntax of source code. A Schema AST Editor allows developers to programmatically analyze, format, and mutate code structure without treating the code as a mere string of text.</li>
<li><strong>The Core Problem:</strong> Manipulating code using Regular Expressions (Regex) or string replacement is fundamentally broken. Regex cannot understand nested scopes, matching brackets, or context (e.g., distinguishing between a variable named <code>let match = true</code> and the keyword <code>match</code>).</li>
<li><strong>The Solution:</strong> Compilers break code down into three steps: Lexing (turning text into tokens), Parsing (turning tokens into an AST), and Generation (turning the AST back into code). By intervening at the AST stage, tools can safely modify the codebase with mathematical precision.</li>
<li><strong>Modern Reality:</strong> Every modern frontend tool—Babel, TypeScript, ESLint, Prettier, and automated Codemods—relies entirely on AST manipulation. It is the invisible engine powering modern developer experience (DX).</li>
</ul>

<h2>Historical Context & The Catalyst: The Regex Trap</h2>
<p>If you worked on a large codebase in the early 2000s, you likely experienced the sheer terror of performing a "Global Find and Replace." Let\'s say your team decided to rename the function <code>getUser()</code> to <code>fetchUser()</code>. You open your IDE, type <code>getUser</code>, and hit Replace All. Instantly, your codebase breaks.</p>

<p>Why? Because string manipulation is dumb. The Regex matched a variable named <code>targetUser()</code>, it matched a comment that said <code>// TODO: getUser from DB</code>, and it missed the cases where <code>get\nUser()</code> was split across two lines. Software engineers quickly realized a terrifying truth: <strong>Code is not a String. Code is a Graph.</strong></p>

<p>Treating code as a string is like trying to perform heart surgery using a chainsaw in the dark. To safely manipulate code, the computer needs to understand the <em>meaning</em> and <em>hierarchy</em> of the text. It needs to know what is a variable, what is a function declaration, and what is merely a comment. This realization led tooling engineers to borrow the oldest trick from the Compiler Engineer\'s handbook: The Abstract Syntax Tree.</p>

<h2>The Academic Breakthrough: From Text to Tree</h2>
<p>In the academic discipline of Compiler Construction, transforming human-readable text into machine-executable instructions is a well-solved problem. The magic happens through a rigorous mathematical pipeline. If we want to build a tool that can automatically format or refactor our code, we must hijack this pipeline.</p>

<h3>Step 1: Lexical Analysis (The Tokenizer)</h3>
<p>The Lexer reads the raw string of code character by character and groups them into "Tokens". For example, the string <code>let x = 5;</code> is converted into an array of tokens: <code>[Keyword(let), Identifier(x), Operator(=), Number(5), Punctuation(;)]</code>. The Lexer removes all whitespace and comments. It doesn\'t care about logic; it only cares about vocabulary.</p>

<h3>Step 2: Syntactic Analysis (The Parser)</h3>
<p>This is where the AST is born. The Parser takes the flat array of tokens and applies the grammar rules of the language (like JavaScript) to build a deeply nested tree structure. Our <code>let x = 5;</code> becomes a JSON-like object:</p>
<pre><code>{
  "type": "VariableDeclaration",
  "kind": "let",
  "declarations": [{
    "type": "VariableDeclarator",
    "id": { "type": "Identifier", "name": "x" },
    "init": { "type": "Literal", "value": 5 }
  }]
}</code></pre>
<p>This is the <strong>Abstract Syntax Tree</strong>. It is "Abstract" because it strips away syntax details like semicolons and spaces, focusing purely on the logical structure of the program.</p>

<h2>Deep Architectural Walkthrough: The Visitor Pattern</h2>
<p>Once you have the AST, how do you modify it? You cannot just write a <code>for</code> loop, because a tree is deeply nested and recursive. Computer scientists use a classic design pattern called the <strong>Visitor Pattern</strong> to traverse the AST.</p>

<p>Imagine the AST as a giant museum with thousands of rooms (nodes). A "Visitor" is an object that walks through every single room. As it enters a room, it checks the sign on the door. If the sign says <code>"VariableDeclaration"</code>, the Visitor triggers a specific function.</p>

<p>Let\'s say you want to write an ESLint rule that bans the use of <code>var</code> and forces developers to use <code>let</code> or <code>const</code>. You write a Visitor that specifically listens for the <code>VariableDeclaration</code> node. When the Visitor enters that node, it checks the <code>kind</code> property. If <code>kind === \'var\'</code>, the Visitor throws an error, or better yet, it mutates the AST directly, changing <code>"kind": "var"</code> to <code>"kind": "let"</code>.</p>

<p>Because the Visitor is operating on the logical tree, it is 100% immune to whitespace, comments, or naming collisions. It will never accidentally rename a variable called <code>varName</code>. It operates with surgical precision.</p>

<h2>Modern Production Reality: Babel, Prettier, and Codemods</h2>
<p>The AST is the invisible bedrock of modern web development. You use it dozens of times a day without realizing it.</p>

<ul>
<li><strong>Babel:</strong> How can you write modern ES6+ JavaScript and have it run on older browsers? Babel parses your ES6 code into an AST, runs Visitors to mutate modern nodes (like Arrow Functions) into older nodes (like standard Functions), and then generates ES5 code from the modified tree.</li>
<li><strong>Prettier:</strong> Why is Prettier the ultimate code formatter? Because it completely ignores your original formatting. Prettier parses your code into an AST, immediately throws away all your spaces, tabs, and line breaks, and then prints the AST back into text using its own strict mathematical layout rules. It guarantees perfect formatting every time.</li>
<li><strong>Codemods (jscodeshift):</strong> When React updates its API, they release a "Codemod". Instead of asking developers to manually update thousands of files, they provide an AST script. You run the script, it parses your entire codebase into ASTs, finds the old API calls, mutates the tree, and rewrites your files. It is automated, massive-scale refactoring.</li>
</ul>

<h2>Expert Critique & Legacy</h2>
<p>The transition from "Code as Text" to "Code as an AST Graph" fundamentally elevated software engineering. It allowed us to build self-healing codebases, aggressive linters, and intelligent IDEs with accurate auto-complete. It removed the human error from refactoring.</p>

<p>However, AST manipulation is not without its dark side. Writing AST transformations (like Babel plugins) is notoriously difficult. The developer must understand the exact specification of the language\'s grammar, which changes frequently. The AST representations between different tools (e.g., Babel\'s AST vs TypeScript\'s AST) are often incompatible, leading to fragmented ecosystems and fragile toolchains.</p>

<p>Despite these complexities, mastering the AST is a superpower. The moment a developer stops seeing their code as a text file and starts seeing it as a traversable, mutable data structure, their ability to engineer tooling and scale architectures grows exponentially. The AST is what allows our code to finally understand itself.</p>
';

$post_en = wp_insert_post([
    'post_title' => 'Code is a Graph, Not a String: The Power of AST Manipulation',
    'post_content' => $content_en,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_en],
    'tags_input' => ['AST', 'Compilers', 'Prettier', 'Babel', 'Codemod']
]);
pll_set_post_language($post_en, 'en');
if ($attach_id) set_post_thumbnail($post_en, $attach_id);

// 4. Content VI (Gergely Orosz Persona)
$content_vi = '
<h2>AI Summary / Executive Abstract (Tóm lược chuẩn SEO)</h2>
<ul>
<li><strong>AST là gì?</strong> Cây cú pháp trừu tượng (Abstract Syntax Tree - AST) là một cấu trúc dữ liệu dạng cây, biểu diễn logic cú pháp của mã nguồn. Nó giúp phần mềm "đọc hiểu" code của bạn không phải như một chuỗi văn bản (string), mà như một bản đồ cấu trúc.</li>
<li><strong>Vấn đề giải quyết:</strong> Việc dùng Regular Expression (Regex) hoặc Find & Replace để sửa code hàng loạt là một thảm họa. Regex rất ngu ngốc, nó không phân biệt được đâu là biến số, đâu là hàm, đâu là đoạn comment, dẫn đến việc thay thế nhầm và làm sập toàn bộ hệ thống.</li>
<li><strong>Giải pháp (Workflow):</strong> Các công cụ hiện đại biến code text thành các Token (Lexing), xếp Token thành cây AST (Parsing), viết code (Visitor) can thiệp thẳng vào các nhánh của cây AST để biến đổi logic một cách chính xác tuyệt đối, sau đó in cây AST ngược trở lại thành text (Generation).</li>
<li><strong>Thực tiễn Production:</strong> Gần 100% công cụ Frontend hiện đại mà bạn đang xài (Babel, TypeScript Compiler, ESLint, Prettier, Codemod) đều chạy bằng sức mạnh của AST. Đây là cỗ máy vô hình đứng sau khái niệm DX (Developer Experience) hoàn hảo ngày nay.</li>
</ul>

<h2>Bối Cảnh Lịch Sử: Cơn Ác Mộng Của Regex Và Lệnh Find & Replace</h2>
<p>Bất kỳ một kỹ sư phần mềm lão làng nào từng làm việc với các hệ thống Legacy (Mã nguồn cũ) khổng lồ vào đầu những năm 2000 đều từng nếm trải cảm giác lạnh gáy khi dùng lệnh <strong>"Global Find and Replace"</strong>. Giả sử sếp yêu cầu bạn đổi tên cái hàm <code>thanhToan()</code> thành <code>xuLyThanhToan()</code> trên toàn bộ 500 file code.</p>

<p>Bạn mở VSCode lên, gõ Regex, ấn nút Replace All. Bùm! Hệ thống sập. Tại sao? Bởi vì việc xử lý code như một chuỗi String (Văn bản thuần túy) là một tội ác. Lệnh Regex của bạn vô tình sửa luôn một biến tên là <code>thanhToanThangNay</code>, nó sửa luôn dòng comment <code>// ghi chu: user thanhToan</code>, và tệ nhất, nó bỏ sót cái hàm <code>thanh\nToan()</code> vì lập trình viên trước đó lỡ bấm Enter xuống dòng ở giữa chữ.</p>

<p>Sự thật tàn khốc mà giới lập trình viên nhận ra là: <strong>Code không phải là Văn bản. Code là một Cây Cấu Trúc (Graph/Tree).</strong></p>

<p>Việc dùng Regex để sửa code giống như bạn nhắm mắt và dùng cưa máy để phẫu thuật não vậy. Để thao tác code an toàn, công cụ của bạn phải "Hiểu" được ngữ nghĩa (Semantics). Nó phải biết cái chữ <code>let</code> là một từ khóa khai báo, <code>x</code> là tên biến, và đoạn text trong ngoặc kép chỉ là string. Để đạt được cảnh giới này, giới kỹ sư Tooling đã phải lục lại cuốn sách cẩm nang lâu đời nhất của Khoa học máy tính: Kỹ thuật thiết kế Trình biên dịch (Compiler Design) và khái niệm AST.</p>

<h2>Đột Phá Học Thuật: Biến Chữ Viết Thành Cây (From Text to Tree)</h2>
<p>Trong thế giới của Trình biên dịch, việc biến một đống chữ do con người viết thành những chỉ thị máy tính là một bài toán đã được giải quyết triệt để thông qua một Pipeline (Đường ống) Toán học cực kỳ nghiêm ngặt. Nếu chúng ta muốn chế tạo một con Bot tự động Format code (như Prettier) hay tự động Fix bug (ESLint), ta phải "bắt cóc" cái Pipeline này.</p>

<h3>Bước 1: Phân tích Từ vựng (Lexical Analysis / Tokenizer)</h3>
<p>Con Bot (Lexer) sẽ đọc chuỗi code của bạn từng ký tự một và gom chúng lại thành các "Từ vựng" (Tokens). Ví dụ chuỗi: <code>let x = 5;</code> sẽ bị băm ra thành một mảng: <code>[Keyword(let), Identifier(x), Operator(=), Number(5), Punctuation(;)]</code>. Lexer cực kỳ lạnh lùng, nó vứt bỏ toàn bộ dấu cách (space) thừa mứa và các đoạn comment. Nó không quan tâm code bạn chạy đúng hay sai, nó chỉ kiểm tra xem bạn có viết sai chính tả hay không.</p>

<h3>Bước 2: Phân tích Cú pháp (Syntactic Analysis / Parser)</h3>
<p>Đây là lúc AST chào đời. Parser lấy cái mảng Token ở trên, áp dụng các quy tắc ngữ pháp (Grammar rules) của ngôn ngữ JavaScript để xây dựng lên một cái Cây có cấu trúc phân cấp sâu thẳm. Câu lệnh <code>let x = 5;</code> của chúng ta biến thành một Object JSON vĩ đại như thế này:</p>
<pre><code>{
  "type": "VariableDeclaration",
  "kind": "let",
  "declarations": [{
    "type": "VariableDeclarator",
    "id": { "type": "Identifier", "name": "x" },
    "init": { "type": "Literal", "value": 5 }
  }]
}</code></pre>
<p>Đây chính là <strong>Abstract Syntax Tree (Cây cú pháp trừu tượng)</strong>. Từ "Trừu tượng" ở đây có nghĩa là nó đã vứt bỏ những chi tiết vụn vặt như dấu chấm phẩy, dấu cách... để chỉ tập trung vào "Cốt lõi Logic" của chương trình.</p>

<h2>Giải Phẫu Kiến Trúc: Tuyệt Kỹ Visitor Pattern</h2>
<p>Khi bạn đã cầm trong tay cái cây AST khổng lồ này rồi, làm sao bạn sửa nó? Bạn không thể viết vòng lặp <code>for</code> đơn giản được vì một cái cây có thể phân nhánh đệ quy sâu hàng trăm tầng. Giới khoa học máy tính dùng một Design Pattern kinh điển: <strong>Mẫu Thiết kế Khách viếng thăm (Visitor Pattern)</strong>.</p>

<p>Hãy tưởng tượng cái cây AST là một bảo tàng khổng lồ với hàng vạn căn phòng (Node). "Visitor" là một ông Thanh tra đi bộ qua TỪNG CĂN PHÒNG MỘT. Trước khi bước vào phòng, ông ta nhìn cái bảng tên treo trước cửa. Nếu bảng tên ghi <code>"VariableDeclaration"</code> (Khai báo biến), ông ta sẽ kích hoạt nghiệp vụ kiểm tra.</p>

<p>Thực tiễn: Bạn muốn viết một rule ESLint cấm team của bạn dùng từ khóa <code>var</code> (bắt buộc phải dùng <code>let</code> hoặc <code>const</code>). Bạn chỉ cần viết một ông Visitor chuyên rình rập ở các Node có bảng tên <code>VariableDeclaration</code>. Khi bắt được Node này, ông ta kiểm tra thuộc tính <code>kind</code>. Nếu thấy <code>kind === \'var\'</code>, ông ta lập tức bắn ra một cái Lỗi (Error) lên màn hình của thằng Dev. Khủng khiếp hơn, ông ta có thể trực tiếp sửa cái Node đó thành <code>"kind": "let"</code> một cách tự động (Tính năng <code>--fix</code> của ESLint).</p>

<p>Bởi vì Visitor can thiệp thẳng vào cấu trúc Logic của Cây, nó đạt độ chính xác 100%. Nó miễn nhiễm hoàn toàn với khoảng trắng, dòng mới, hay comment. Nó không bao giờ sửa nhầm một cái biến tên là <code>varName</code>. Sự chính xác của nó mang đẳng cấp phẫu thuật ngoại khoa.</p>

<h2>Thực Tiễn Production: Prettier, Babel và Ma thuật Codemod</h2>
<p>AST chính là lớp nền móng tàng hình của toàn bộ ngành Web Development hiện đại. Bạn xài nó hàng chục lần mỗi ngày mà không hề hay biết.</p>

<ul>
<li><strong>Babel:</strong> Làm sao bạn có thể viết code ES6/ES7 cực xịn (ví dụ: Arrow Function) mà vẫn chạy mượt mà trên cái trình duyệt Internet Explorer 11 cùi bắp? Babel chính là người hùng. Nó Parse code ES6 của bạn thành AST, dùng Visitor để bẻ gãy các Node hiện đại (Arrow Function), biến đổi (Mutate) chúng thành các Node cổ đại (Standard Function), rồi In cái cây AST đó ngược ra thành code ES5.</li>
<li><strong>Prettier:</strong> Tại sao Prettier được mệnh danh là kẻ độc tài tối cao của việc Format Code? Bởi vì nó hoàn toàn KHÔNG TÔN TRỌNG cách bạn viết code. Prettier parse code của bạn thành AST, vứt thẳng vào sọt rác toàn bộ các dấu Space, Tab, Enter mà bạn gõ. Sau đó, nó in cái cây AST đó ra lại thành Text dựa trên bộ quy tắc Toán học cứng nhắc của riêng nó. Kết quả: 1000 ông Dev trong công ty bạn sẽ viết ra một chuẩn Format giống nhau y đúc đến từng milimet.</li>
<li><strong>Codemods (jscodeshift):</strong> Khi React ra mắt Hooks, họ cập nhật API. Làm sao để nâng cấp 10.000 file code từ Class Component sang Functional Component? Thay vì bắt Dev ngồi sửa tay mất nửa năm, React team tung ra một "Codemod". Đó là một đoạn script AST. Bạn chạy script, nó quét sạch dự án của bạn, dựng cây AST, chém đứt các Node Class, ghép các Node Function vào, và lưu lại file. Bạn vừa thực hiện một cuộc đại phẫu thuật Refactoring quy mô khổng lồ chỉ trong 1 nốt nhạc.</li>
</ul>

<h2>Bình Luận Chuyên Gia & Di Sản (Expert Critique & Legacy)</h2>
<p>Sự chuyển mình từ tư duy "Code là Văn Bản" sang tư duy "Code là Cấu Trúc AST" đã nâng tầm ngành Kỹ thuật phần mềm lên một đẳng cấp mới. Nó cho phép chúng ta chế tạo ra những đoạn code có khả năng tự sửa lỗi (Self-healing), những con Linter hung hãn, và những chiếc IDE (như VSCode) với khả năng Auto-complete chính xác đến rợn người. Nó loại bỏ hoàn toàn yếu tố sai sót của con người ra khỏi việc Refactoring.</p>

<p>Tuy nhiên, làm việc với AST không dành cho tay mơ. Việc viết các AST Plugins (như Babel Plugin) là một cơn ác mộng cực độ. Lập trình viên phải học thuộc lòng đặc tả ngữ pháp (Grammar Spec) của ngôn ngữ, thứ mà thay đổi liên tục mỗi năm. Thêm vào đó, Cây AST của các công cụ khác nhau thường... đánh lộn nhau (AST của Babel khác AST của TypeScript), dẫn đến một hệ sinh thái Tooling vô cùng phân mảnh và dễ gãy vỡ.</p>

<p>Nhưng vượt lên trên tất cả sự phức tạp đó, việc thông thạo AST mang lại cho bạn một siêu năng lực. Khoảnh khắc mà một lập trình viên ngừng nhìn file code như những dòng text vô hồn, và bắt đầu nhìn nó như một cấu trúc dữ liệu sống động có thể uốn nắn được, khả năng tư duy hệ thống và xây dựng Tooling của họ sẽ tăng lên theo cấp số nhân. AST chính là thứ vũ khí tối thượng giúp mã nguồn cuối cùng cũng có thể "thấu hiểu" được chính nó.</p>
';

$post_vi = wp_insert_post([
    'post_title' => 'Code Là Cây, Không Phải Văn Bản: Sức Mạnh Tối Thượng Của AST Manipulation',
    'post_content' => $content_vi,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_vi],
    'tags_input' => ['AST', 'Compilers', 'Prettier', 'Babel', 'Codemod']
]);
pll_set_post_language($post_vi, 'vi');
if ($attach_id) set_post_thumbnail($post_vi, $attach_id);

// 5. Content JA (Julia Evans Persona)
$content_ja = '
<h2>AI向け要約 / エグゼクティブ・アブストラクト 🤖</h2>
<ul>
<li><strong>これは何？</strong> 抽象構文木（Abstract Syntax Tree - AST）は、ソースコードの論理的な構造をツリー（木）の形で表現したデータ構造です。ASTを使うと、プログラムをただの「文字の羅列」としてではなく、「意味を持った構造」として安全に分析・変形（フォーマット）することができます。</li>
<li><strong>根本的な問題：</strong> 正規表現（Regex）や文字列の置換（Find & Replace）を使って大量のコードを一斉に変更しようとするのは危険すぎます。正規表現は「変数名」と「コメント」の違いや、カッコの対応関係などの「文脈（コンテキスト）」を理解できないため、システムを容易に破壊してしまいます。</li>
<li><strong>解決策：</strong> 現代のツールは、コンパイラの技術を借りてコードを3つの段階で処理します。文字を単語に分ける（Lexing）、単語からツリー構造を作る（Parsing）、そしてツリーの枝を直接書き換えてから再び文字に戻す（Generation）。ツリーを直接操作することで、数学的な精度でコードを安全に書き換えることができます。</li>
<li><strong>現代の真実：</strong> Prettier、ESLint、Babel、TypeScript、そして自動リファクタリングツール（Codemod）など、あなたが毎日使っているフロントエンドの便利ツールの「100%」が、このASTの魔法をエンジンとして動いています。</li>
</ul>

<h2>歴史的背景：正規表現という名のチェーンソー 🪚</h2>
<p>2000年代初頭の巨大なプロジェクトで働いたことがあるエンジニアなら、「グローバルな検索と置換（Global Find and Replace）」を実行するときの、あの冷や汗が出るような恐怖を知っているでしょう。たとえば、チームの方針で <code>getUser()</code> という関数の名前を <code>fetchUser()</code> に一斉変更することになったとします。あなたはエディタを開き、<code>getUser</code> と入力して「すべて置換」ボタンを押します。すると次の瞬間、システムが完全にぶっ壊れます。</p>

<p>なぜでしょうか？ それは、文字列操作（String Manipulation）が絶望的に「おバカ」だからです。正規表現は、<code>targetUser()</code> という別の変数名の中身まで巻き添えにし、<code>// TODO: getUser 関数を後で作る</code> というただのコメントまで書き換え、さらには改行を挟んで <code>get\nUser()</code> と書かれていた本当の関数を見逃してしまいます。ソフトウェアエンジニアたちは、血の涙を流しながら恐ろしい真実に気づきました。<strong>「コードは文字列（String）ではない。コードはグラフ（Graph：構造）なのだ」</strong>ということに。</p>

<p>コードを文字列として扱うことは、暗闇の中でチェーンソーを使って心臓手術をするようなものです。コードを安全に操作するためには、コンピュータ自身がテキストの「意味（Semantics）」と「階層（Hierarchy）」を理解しなければなりません。どれが変数で、どれが関数で、どれが単なるコメントなのかを知る必要があるのです。この悟りを開いたツールの開発者たちは、コンパイラエンジニアの古文書から最も強力な黒魔術を借りてきました。それが「抽象構文木（AST）」です。</p>

<h2>学術的ブレイクスルー：テキストからツリーへの変換 🌲</h2>
<p>「人間が書いたテキストを、コンピュータが理解できる命令に変換する」という作業は、コンパイラ構築（Compiler Construction）という学問分野において、すでに数学的に完全に解決された問題です。Prettierのような「コードを自動で整形する魔法のツール」を作りたいなら、このコンパイラのパイプラインをハイジャックすればよいのです。</p>

<h3>ステップ1：字句解析（字句解析器 - Lexer / Tokenizer）</h3>
<p>Lexer（レキサー）は、コードの文字列を1文字ずつ読み込み、それらを「意味のある単語（トークン）」にグループ化します。たとえば、<code>let x = 5;</code> という文字列は、<code>[キーワード(let), 識別子(x), 演算子(=), 数字(5), 記号(;)]</code> という配列に切り刻まれます。Lexerはとても冷酷で、人間が読みやすくするために入れたスペースやコメントをすべて無慈悲に捨て去ります。文法が正しいかどうかも気にしません。ただ「単語帳」を作るだけです。</p>

<h3>ステップ2：構文解析（パーサー - Parser）</h3>
<p>ここからがASTの誕生です。パーサーは、平坦なトークンの配列を受け取り、JavaScriptなどの言語の文法ルール（Grammar）を当てはめ、深くネストされたツリー構造（木）を組み立てます。先ほどの <code>let x = 5;</code> は、次のような巨大なJSONオブジェクトに生まれ変わります。</p>
<pre><code>{
  "type": "VariableDeclaration",
  "kind": "let",
  "declarations": [{
    "type": "VariableDeclarator",
    "id": { "type": "Identifier", "name": "x" },
    "init": { "type": "Literal", "value": 5 }
  }]
}</code></pre>
<p>これが<strong>「抽象構文木（Abstract Syntax Tree）」</strong>です。「抽象（Abstract）」と呼ばれる理由は、セミコロンやスペースといった表面的な見栄えを取り除き、プログラムの「論理的な骨格」だけを純粋に抽出しているからです。</p>

<h2>アーキテクチャの徹底解剖：ビジター・パターンのお客さん 🚶‍♂️</h2>
<p>さて、この巨大なツリー（AST）を手に入れた後、どうやってそれを変更すればよいでしょうか？ ツリーはマトリョーシカのように何重にも入れ子になっているため、単純な <code>for</code> ループでは処理できません。コンピュータサイエンスの世界では、これを解決するために<strong>「ビジター・パターン（Visitor Pattern）」</strong>という古典的なデザインパターンを使います。</p>

<p>ASTを「何万もの部屋（ノード）がある巨大な美術館」だと想像してください。「ビジター（訪問者）」は、そのすべての部屋を順番に歩いて回るロボットです。ビジターは部屋に入るたびに、ドアの表札を確認します。もし表札に <code>"VariableDeclaration"（変数宣言）</code> と書かれていれば、あらかじめ設定しておいた特定のプログラムを起動します。</p>

<p>実例を挙げましょう。あなたのチームで「<code>var</code> の使用を禁止し、絶対に <code>let</code> か <code>const</code> を使わせる」というESLintのルールを作りたいとします。あなたは、<code>VariableDeclaration</code> の表札を探すビジターを書くだけでよいのです。ビジターがその部屋に入り、変数の種類（<code>kind</code>）をチェックして <code>kind === \'var\'</code> だった場合、ビジターはエラーを投げます。さらに凄いことに、ビジターはそのノードの値を直接 <code>"kind": "let"</code> に書き換えてしまうこともできます（これがESLintの自動修正機能です）。</p>

<p>ビジターは論理的なツリーの上を歩いているため、スペースやコメントの有無、改行の罠などに絶対に騙されません。<code>varName</code> という名前の変数を間違えて書き換えることも100%ありません。それは外科手術のような、完璧な精度を持っています。</p>

<h2>現代のフロントエンドの真実：Prettier、Babel、そしてCodemod 🚀</h2>
<p>ASTは、現代のWeb開発を根底から支える見えない基盤です。あなたが意識していなくても、毎日数十回はこの魔法の恩恵を受けています。</p>

<ul>
<li><strong>Babel：</strong> 最新のES6のコード（アロー関数など）を書いても、なぜ古いInternet Explorerで動くのでしょうか？ BabelがあなたのコードをASTに変換し、ビジターを使って未来のノード（アロー関数）を破壊し、古いノード（昔の関数）に外科手術（Mutate）で作り変え、最後にそのツリーから古いコードを生成しているからです。</li>
<li><strong>Prettier：</strong> なぜPrettierは絶対的なコードフォーマッターとして君臨しているのでしょうか？ それは、彼らが「あなたが書いた元のフォーマット」を完全に無視するからです。PrettierはコードをASTに変換した瞬間、あなたが書いたスペース、タブ、改行をすべてゴミ箱に捨てます。そして、ツリーの論理構造だけを見て、独自の厳格な数学的ルールに従ってコードを「一から印刷し直す」のです。だからこそ、誰が書いても100%同じ見た目になります。</li>
<li><strong>Codemod（自動リファクタリング）：</strong> Reactが大型アップデートを行ったとき、何万行ものコードを手作業で書き換えるのは不可能です。そこで公式チームは「Codemod」というASTスクリプトを配布します。このスクリプトを実行すると、プロジェクト全体のコードがASTに変換され、古いAPIのツリー構造が一瞬で新しい構造に組み替えられ、ファイルが上書きされます。これは、人間の手では不可能な超大規模な自動リファクタリングです。</li>
</ul>

<h2>専門家による批評と、ASTが遺したレガシー 🏛️</h2>
<p>「コードをテキストとして扱う」時代から、「コードをASTグラフとして扱う」時代への移行は、ソフトウェアエンジニアリングを根本から進化させました。自己修復するコードベース、獰猛なリンター（Lint）、そして完璧な自動補完（Auto-complete）を備えた知的エディタ（VSCodeなど）を作ることができるようになったのです。リファクタリングから「人間のミス」という不確定要素を完全に排除しました。</p>

<p>しかし、ASTの操作には暗黒面もあります。BabelプラグインのようなAST変換ツールを自分で書くのは、発狂するほど難しいのです。開発者は言語の厳密な文法仕様（Grammar Spec）を完全に理解しなければなりませんが、仕様は毎年複雑に変化します。さらに、PrettierのAST、BabelのAST、TypeScriptのASTは微妙に規格が異なり、互換性がないため、ツール間の連携が壊れやすい（Fragile）というエコシステムの分断を引き起こしています。</p>

<p>こうした複雑さはありますが、ASTをマスターすることはエンジニアにとって「スーパーパワー（超能力）」を手に入れることを意味します。開発者が自分の書いたコードをただの文字の羅列として見るのをやめ、それを「探索可能で、自在に変形できるデータ構造（ツリー）」として見ることができるようになった瞬間、ツールを設計する能力とシステムをスケールさせる能力は爆発的に向上します。ASTとは、私たちの書いたコードが、ついに「自分自身を理解する」ことを可能にした、究極の魔法なのです。</p>
';

$post_ja = wp_insert_post([
    'post_title' => 'コードは文字列ではない、グラフだ：AST（抽象構文木）が支える現代の魔法',
    'post_content' => $content_ja,
    'post_status' => 'publish',
    'post_author' => 1,
    'post_category' => [$cat_ja],
    'tags_input' => ['AST', 'Compilers', 'Prettier', 'Babel', 'Codemod']
]);
pll_set_post_language($post_ja, 'ja');
if ($attach_id) set_post_thumbnail($post_ja, $attach_id);

// 6. Link Translations
pll_save_post_translations([
    'en' => $post_en,
    'vi' => $post_vi,
    'ja' => $post_ja
]);

echo "Successfully generated Extreme Deep Dive for Cluster 3 (Schema AST Editor)!\n";
