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
        body, html { 
            font-family: 'Kanit', sans-serif; 
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden; /* ไม่ให้มี Scrollbar */
        }
    </style>
</head>
<body class="relative w-full h-full bg-gray-100">

    <!-- แผงควบคุมลอยตัว (Floating Panel) -->
    <div class="absolute top-4 left-4 z-10 w-80 max-w-[calc(100vw-2rem)] bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl overflow-hidden border border-gray-200 transition-all hover:bg-white">
        <div class="p-5 bg-blue-600 text-white">
            <h1 class="text-2xl font-semibold mb-1">Project PVET</h1>
            <p class="text-blue-100 text-sm">ระบบแสดงแผนที่แบบไร้รอยต่อ</p>
        </div>
        
        <div class="p-5">
            <label for="province" class="block text-sm font-medium text-gray-700 mb-2">เลือกจังหวัดเพื่อย้ายขอบเขตแผนที่</label>
            <div class="relative">
                <select id="province" onchange="changeProvince(this.value)" 
                        class="block w-full pl-4 pr-10 py-3 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-md rounded-xl border bg-gray-50 hover:bg-white transition-colors cursor-pointer appearance-none shadow-sm">
                    <?php foreach($provinces as $province): ?>
                        <option value="<?php echo htmlspecialchars($province['name']); ?>" 
                            <?php echo $selected_province === $province['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($province['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-100 text-center text-xs text-gray-400">
                ออกแบบโดย ผอ.มารุต ศิริธร<br/>พัฒนาโดย PRCT-Soft
            </div>
        </div>
    </div>

    <!-- พื้นที่แสดงแผนที่แบบเต็มหน้าจอ -->
    <div class="w-full h-full">
        <?php 
        $api_key = (defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY)) ? GOOGLE_MAPS_API_KEY : '';
        if ($api_key): 
        ?>
            <!-- แผนที่แบบที API Key -->
            <iframe id="mapIframe"
                class="w-full h-full border-0 block"
                allowfullscreen
                src="https://www.google.com/maps/embed/v1/place?key=<?php echo $api_key; ?>&q=<?php echo urlencode($selected_province . ' ประเทศไทย'); ?>&language=th&zoom=11">
            </iframe>
        <?php else: ?>
            <!-- แผนที่แบบไม่มี API Key -->
            <iframe id="mapIframe"
                class="w-full h-full border-0 block"
                scrolling="no" 
                marginheight="0" 
                marginwidth="0" 
                src="https://maps.google.com/maps?q=<?php echo urlencode($selected_province . ' ประเทศไทย'); ?>&t=&z=11&ie=UTF8&iwloc=&output=embed">
            </iframe>
        <?php endif; ?>
    </div>

    <script>
        // ฟังก์ชันโหลดแผนที่ใหม่โดยไม่รีเฟรชหน้า (Iframe src update)
        function changeProvince(provinceName) {
            const apiKey = "<?php echo $api_key; ?>";
            const iframe = document.getElementById('mapIframe');
            
            // ทำให้ชื่อจังหวัดเป็น URL format
            const query = encodeURIComponent(provinceName + ' ประเทศไทย');
            
            if (apiKey) {
                iframe.src = `https://www.google.com/maps/embed/v1/place?key=${apiKey}&q=${query}&language=th&zoom=11`;
            } else {
                iframe.src = `https://maps.google.com/maps?q=${query}&t=&z=11&ie=UTF8&iwloc=&output=embed`;
            }
            
            // อัปเดต URL บน Browser ตามไปด้วยเพื่อให้แชร์ลิงก์หรือกด Refresh ได้ถูกจังหวัด (Optional)
            const url = new URL(window.location);
            url.searchParams.set('province', provinceName);
            window.history.pushState({}, '', url);
        }
    </script>
</body>
</html>
