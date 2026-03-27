<?php
// นำเข้าไฟล์ตั้งค่าเพื่อซ่อนรหัสผ่านและ API Key
require_once 'config.php';

// เชื่อมต่อฐานข้อมูล
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($db->connect_errno > 0) {
    die('Unable to connect to database [' . $db->connect_error . ']');
}
$db->set_charset("utf8mb4");

// ดึงข้อมูลจังหวัดจากตาราง tempprovince
$provinces = [];
$sql = "SELECT DISTINCT CH_ID, CHANGWAT_T FROM tempprovince ORDER BY CHANGWAT_T ASC";
$result = $db->query($sql);

if ($result) {
    while($row = $result->fetch_assoc()) {
        $provinces[] = [
            'id' => $row['CH_ID'],
            // ลบคำว่า "จ. " ออกถ้ามี
            'name' => str_replace("จ. ", "", $row["CHANGWAT_T"])
        ];
    }
}
$db->close();

// รับค่าจังหวัดที่เลือก (ค่าเริ่มต้นคือ กรุงเทพมหานคร)
$selected_province = isset($_GET['province']) ? $_GET['province'] : 'กรุงเทพมหานคร';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PVET - แผนที่ประเทศไทย</title>
    <!-- ใช้ Tailwind CSS สำหรับความสวยงาม -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; }
        /* ซ่อน scrollbar ใน iframe */
        iframe { border-radius: 0.5rem; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-5xl bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="p-6 md:p-8 bg-blue-600 text-white flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-semibold mb-2">Project PVET</h1>
                <p class="text-blue-100">ระบบแสดงแผนที่ตามจังหวัด</p>
            </div>
            <div class="hidden md:block">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-blue-200 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                </svg>
            </div>
        </div>
        
        <div class="p-6 md:p-8">
            <form method="GET" action="" class="mb-6" id="provinceForm">
                <label for="province" class="block text-sm font-medium text-gray-700 mb-2">เลือกจังหวัดที่คุณต้องการดูแผนที่</label>
                <div class="relative">
                    <select name="province" id="province" onchange="document.getElementById('provinceForm').submit()" 
                            class="block w-full pl-4 pr-10 py-3 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-md rounded-lg border bg-gray-50 hover:bg-white transition-colors cursor-pointer appearance-none">
                        <?php foreach($provinces as $province): ?>
                            <option value="<?php echo htmlspecialchars($province['name']); ?>" 
                                <?php echo $selected_province === $province['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($province['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
            </form>

            <div class="w-full h-[500px] md:h-[600px] rounded-xl overflow-hidden shadow-inner border border-gray-200 bg-gray-100 flex items-center justify-center relative">
                <?php if (defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY)): ?>
                    <!-- แผนที่ Google Map แบบใช้ API Key (ทางการและเสถียร) -->
                    <iframe 
                        class="absolute inset-0 w-full h-full"
                        frameborder="0" style="border:0"
                        allowfullscreen
                        src="https://www.google.com/maps/embed/v1/place?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&q=<?php echo urlencode($selected_province . ' ประเทศไทย'); ?>&language=th&zoom=11">
                    </iframe>
                <?php else: ?>
                    <!-- แผนที่ Google Map แบบไม่มี API Key (ทำงานได้แต่อาจจะถูกจำกัดจาก Google) -->
                    <iframe 
                        class="absolute inset-0 w-full h-full"
                        frameborder="0" 
                        scrolling="no" 
                        marginheight="0" 
                        marginwidth="0" 
                        src="https://maps.google.com/maps?q=<?php echo urlencode($selected_province . ' ประเทศไทย'); ?>&t=&z=11&ie=UTF8&iwloc=&output=embed">
                    </iframe>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="bg-gray-100 px-6 py-4 text-center text-gray-500 text-sm border-t border-gray-200 mt-auto">
            <p>ออกแบบโดย ผอ.มารุต ศิริธร พัฒนาโดย PRCT-Soft</p>
        </div>
    </div>
</body>
</html>
