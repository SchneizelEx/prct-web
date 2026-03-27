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

// --- ส่วนบริการข้อมูล AJAX ขอดึงพิกัดโรงเรียนตามจังหวัด ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_schools') {
    $provinceName = isset($_GET['province']) ? $_GET['province'] : '';
    
    // ค้นหาโรงเรียนโดยเชื่อมตาราง school กับ tempprovince ผ่านรหัสตำบล (TA_ID)
    $sqlAjax = "SELECT s.Name, s.Address, s.Telephone, s.Website, s.Latitude, s.Longitude 
            FROM school s
            INNER JOIN tempprovince t ON s.SubDistrictId = t.TA_ID
            WHERE t.CHANGWAT_T LIKE CONCAT('%', ?, '%') 
            AND s.Latitude IS NOT NULL 
            AND s.Longitude IS NOT NULL
            AND s.Latitude != 0 AND s.Longitude != 0";
            
    $stmt = $db->prepare($sqlAjax);
    $stmt->bind_param("s", $provinceName);
    $stmt->execute();
    $resultAjax = $stmt->get_result();
    
    $schoolsData = [];
    if ($resultAjax) {
        while($row = $resultAjax->fetch_assoc()) {
            $schoolsData[] = [
                'name' => $row['Name'],
                'address' => $row['Address'],
                'telephone' => $row['Telephone'],
                'website' => $row['Website'],
                'lat' => floatval($row['Latitude']),
                'lng' => floatval($row['Longitude'])
            ];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'data' => $schoolsData]);
    $db->close();
    exit;
}
// --- จบส่วน AJAX ---

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
            <h1 class="text-2xl font-semibold mb-1">PVET MAP</h1>
            <p class="text-blue-100 text-sm">ระบบสารสนเทศสถานศึกษาอาชีวศึกษาเอกชน</p>
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
    <div id="map" class="w-full h-full bg-gray-200">
        <!-- Google Maps API จะมาวาดแผนที่ลงใน Div นี้ -->
    </div>

    <script>
        let map;
        let markers = [];
        let geocoder;
        
        // ค่าเริ่มต้น
        const initialProvince = "<?php echo $selected_province; ?>";

        function initMap() {
            // สร้างแผนที่ (ซ่อนปุ่มปรับแต่งต่างๆ ที่ไม่จำเป็นเพื่อความคลีน)
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 10,
                center: { lat: 13.736717, lng: 100.523186 }, // กรุงเทพมหานครเป็นค่าตั้งต้น
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false
            });
            geocoder = new google.maps.Geocoder();
            
            // โหลดข้อมูลโรงเรียนของจังหวัดเริ่มต้น
            changeProvince(initialProvince, true);
        }

        // ฟังก์ชันลบหมุดเก่าเคลียร์ทิ้ง
        function clearMarkers() {
            for (let i = 0; i < markers.length; i++) {
                markers[i].setMap(null);
            }
            markers = [];
        }

        // ฟังก์ชันโหลดสถานที่ใหม่และปักหมุดด้วย AJAX
        function changeProvince(provinceName, isInitialLoad = false) {
            if (!isInitialLoad) {
                const url = new URL(window.location);
                url.searchParams.set('province', provinceName);
                window.history.pushState({}, '', url);
            }

            // เลื่อนแผนที่ไปที่ใจกลางจังหวัดด้วย Geocoder
            geocoder.geocode({ 'address': provinceName + ' ประเทศไทย' }, function(results, status) {
                if (status === 'OK') {
                    map.setCenter(results[0].geometry.location);
                    // ถ้ายังไม่มีหมุดเลย (หรือเน็ตช้า) ให้ซูมไปที่ระดับจังหวัดก่อน
                    if (markers.length === 0) {
                        map.fitBounds(results[0].geometry.viewport);
                    }
                }
            });

            // ดึงข้อมูลโรงเรียนจากระบบ Backend ด้วย AJAX PHP ตัวเอง
            fetch(`index.php?ajax=get_schools&province=${encodeURIComponent(provinceName)}`)
                .then(response => response.json())
                .then(data => {
                    clearMarkers(); // ลบพิกัดเก่าก่อน
                    if (data.status === 'success' && data.data.length > 0) {
                        const infoWindow = new google.maps.InfoWindow();
                        const bounds = new google.maps.LatLngBounds(); // ใช้สำหรับคำนวณระยะขอบที่ครอบคลุมทุกหมุด
                        
                        // วนลูปสร้างหมุดโรงเรียน
                        data.data.forEach(school => {
                            const position = { lat: school.lat, lng: school.lng };
                            const marker = new google.maps.Marker({
                                position: position,
                                map: map,
                                title: school.name,
                                animation: google.maps.Animation.DROP
                            });
                            
                            // คลิกที่หมุดแล้วแสดงข้อมูลบน Popup
                            marker.addListener("click", () => {
                                let contentString = `<div class="p-4 font-kanit min-w-[200px] max-w-[320px]">`;
                                contentString += `<h3 class="text-blue-600 font-semibold text-lg mb-2 border-b border-gray-100 pb-2">${school.name}</h3>`;
                                
                                if (school.address && school.address.trim() !== '') {
                                    contentString += `<div class="text-sm text-gray-700 mb-1 flex items-start"><span class="mr-2">📍</span> <span>${school.address}</span></div>`;
                                }
                                if (school.telephone && school.telephone.trim() !== '') {
                                    contentString += `<div class="text-sm text-gray-700 mb-1 flex items-start"><span class="mr-2">📞</span> <span>${school.telephone}</span></div>`;
                                }
                                if (school.website && school.website.trim() !== '') {
                                    let webUrl = school.website.startsWith('http') ? school.website : 'http://' + school.website;
                                    contentString += `<div class="text-sm text-gray-700 mb-1 flex items-start"><span class="mr-2">🌐</span> <a href="${webUrl}" target="_blank" class="text-blue-500 hover:text-blue-700 underline break-all">${school.website}</a></div>`;
                                }
                                
                                contentString += `</div>`;
                                infoWindow.setContent(contentString);
                                infoWindow.open(map, marker);
                            });
                            
                            markers.push(marker);
                            bounds.extend(position);
                        });
                        
                        // ปรับมุมมอง (Zoom/Center) ให้พอดีกับอาณาเขตของโรงเรียนทั้งหมดที่โหลดมา
                        if (markers.length > 0) {
                            map.fitBounds(bounds);
                            // อย่าซูมใกล้เกินไปถ้ามีแค่หมุดเดียว
                            const listener = google.maps.event.addListener(map, "idle", function() { 
                                if (map.getZoom() > 16) map.setZoom(16); 
                                google.maps.event.removeListener(listener); 
                            });
                        }
                    }
                })
                .catch(error => console.error('Error fetching schools:', error));
        }
    </script>
    
    <?php $api_key = (defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY)) ? GOOGLE_MAPS_API_KEY : ''; ?>
    <!-- โหลด Google Maps API ของจริง (ถ้าไม่ใส่ key ระบบจะแจ้งเตือน For Development Purposes Only แต่ยังใช้งานได้) -->
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $api_key; ?>&callback=initMap&language=th&libraries=places"></script>
</body>
</html>
