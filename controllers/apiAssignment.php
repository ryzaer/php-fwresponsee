<?php

class apiAssignment extends \Router
{
    function assign($slug)
    {
        session_start();
        $http_code = $this->http->code;
        $akses_url = null;
        $token = null;
        $limit = 3600/4; // limit login 1 jam / 4 = 15 menit
        $pdo = $this->dbConnect();
        // memerlukan token dari client side
        $hash['hash'] = [];
        if($slug->arg === 'status'){
            $login_msg = 'Status : Anda belum login!';
            $token = $_POST['token'] ?? '';
            $http_code = 403;
            if(!empty($_SESSION['token']) && $_SESSION['token'] === $token){
                $check = json_decode(\Crypto\ssl::decrypt($token) ?? '[]',true);
                $stm = $pdo->select('tbuser[~id,catatan,username~]', ['id' => $check['id']]);
                if($stm){
                    $args = json_decode($stm[0]['catatan'],true);
                    $hash['hash'] =base64_encode(json_encode([
                        'id' => $stm[0]['id'],
                        'nick' => $stm[0]['username'],
                        'nama' => $args ? $args[0]:'',
                        'hp' => $args ? $args[1]:'',
                        'email' => $args ? $args[2]:''
                    ]));
                }
                if($check){
                    $now = time();
                    if ($check['time'] >= $now) {
                        $login_msg = 'Status : Token Valid!';
                        $http_code = $this->http->code;
                        $check['time'] = $now + $limit;
                        $akses_url = $check['path'];
                        // perbaharui token
                        $token = \Crypto\ssl::encrypt(json_encode($check));
                        $_SESSION['token'] = $token;
                    } else {
                        $login_msg = 'Status : Token Expired!';
                        $akses_url = null;
                        $token = null;
                        unset($_SESSION['token']);
                    }
                    
                }
            }
        }
        // generate token dari form login
        if($slug->arg === 'login'){
            $login_msg = "Form Login tidak boleh kosong!";            
            $http_code = 401;
            // Validasi Username dan Password
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';   

            if(!empty($username) && !empty($password)){
                $pdo = $this->dbConnect();
                $chk = $pdo->select('tbuser[~id,level,akses~]', [
                    'username' => $username,
                    'password' => $password
                ]);
                if($chk){
                    $http_code = $this->http->code;
                    $akses_url = explode(',',$chk[0]['akses'])[0];
                    // buat token sesuai dengan limit waktu yang ditentukan
                    $token = \Crypto\ssl::encrypt(json_encode(['id' => $chk[0]['id'],'role'=>$chk[0]['level'],'path'=>$akses_url,'time'=>time() + $limit]));
                    // Validasi Captcha terakhir
                    $r_captcha = strtoupper(trim($_POST['captcha'] ?? ''));
                    $s_captcha = strtoupper($_SESSION['captcha'] ?? '');
                    $http_code = 400;
                    $login_msg = 'Kode Validasi salah!';
                    if (!empty($r_captcha) && !empty($s_captcha)) {
                        if($r_captcha === $s_captcha){
                            // check/buat table tbskck_reg jika betul bisa login
                            $thn = date('Y');
                            $pdo->exec("
                                CREATE TABLE IF NOT EXISTS tbskck_reg_$thn (
                                    id_reg INT AUTO_INCREMENT PRIMARY KEY,
                                    id_biodata INT NOT NULL,
                                    tgl_terbit DATE NOT NULL,
                                    id_kep SMALLINT(3) NOT NULL,
                                    id_user SMALLINT(3) NOT NULL,
                                    sync_status TINYINT(1) NOT NULL
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                            ");
                            $login_msg = 'Login Valid!';
                            $_SESSION['token'] = $token;
                            $http_code = $this->http->code;
                        }
                    }
                }else{
                    $login_msg = 'Username atau Password salah!';
                }
            }
        }
        if($slug->arg === 'logout'){
            $login_msg = "Logout Valid!";            
            if(!empty($_SESSION['token'])){
                unset($_SESSION['token']);
            }   
        }

        $this->apiResponse($http_code,array_merge([
            'assign' => $akses_url,
            'token' => $token
        ],$hash),[
            'message' => $login_msg
        ]);

    }

    function captcha($slug)
    {
// CONTOH FORM
// <form id="formCaptcha">
//   <p>Masukkan kode di bawah:</p>
//   <img id="captcha-img" style="border-radius: 4px;border: 1px solid black;" src="captcha/{{@time_script}}.png" alt="captcha" style="cursor:pointer"><br>
//   <input type="text" name="captcha" placeholder="Tulis kode di atas"><br>
//   <button type="submit" class="btn btn-info">Kirim</button>
//   <div id="hasil"></div>
// </form>

// <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
// <script>
//   $('#captcha-img').click(function () {
//     $(this).attr('src', 'captcha/' + String(Math.floor(Date.now())).substr(5) + '.png'); // refresh
//   });
//   $('#formCaptcha').submit(function (e) {
//     e.preventDefault();
//     $.post('captcha/verify', $(this).serialize(), function (res) {
//       $('#hasil').html(res);
//     });
//     $('#captcha-img').attr('src', 'captcha/' + String(Math.floor(Date.now())).substr(5) + '.png');
//   });
// </script>
// if($slug->arg == 'verify') {
//     $input = strtoupper(trim($_POST['captcha'] ?? ''));
//     $kode = strtoupper($_SESSION['captcha'] ?? '');
//     if ($input === $kode) {
//         echo "<span style='color: green;'>✔ CAPTCHA benar</span>";
//     } else {
//         echo "<span style='color: red;'>✘ CAPTCHA salah</span>";
//     }            
//     return ;
// }
        
        session_start();
        header("Content-type: image/png");

        // Ukuran gambar kecil (seperti yang kamu pakai)
        $width = 90;
        $height = 30;
        $image = imagecreatetruecolor($width, $height);

        // Warna
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 83, 83, 83); // dark grey
        $gray = imagecolorallocate($image, 150, 150, 150);

        // Latar belakang putih
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        // Tambahkan garis acak sebagai noise
        for ($i = 0; $i < 5; $i++) {
            $lineColor = imagecolorallocate($image, rand(100,200), rand(100,200), rand(100,200));
            imageline($image, rand(0,$width), rand(0,$height), rand(0,$width), rand(0,$height), $lineColor);
        }

        // Tambahkan titik noise
        for ($i = 0; $i < 100; $i++) {
            $dotColor = imagecolorallocate($image, rand(180,255), rand(180,255), rand(180,255));
            imagesetpixel($image, rand(0, $width), rand(0, $height), $dotColor);
        }

        // Teks acak
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $text = '';
        for ($i = 0; $i < 5; $i++) {
            $text .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        $_SESSION['captcha'] = $text;

        // Posisi awal teks
        $x = 11;
        $y = 5;

        // Tampilkan teks huruf per huruf agar bisa dibuat random posisi sedikit
        for ($i = 0; $i < strlen($text); $i++) {
            $fontSize = rand(3, 5); // ukuran bervariasi
            $offsetY = rand(0, 5);  // goyang vertikal
            imagestring($image, $fontSize, $x, $y + $offsetY, $text[$i], $black);
            $x += 15; // geser ke kanan
        }

        // Output gambar PNG
        imagepng($image);
        imagedestroy($image);

    }
    function operator(){
        session_start();
        $rslt=[]; 
        $msgs='Token tidak valid!';
        $type='warning';
        if($this->fn->check_token()){
            $msgs='Pengisian tidak valid!';
            $mode= !empty($_REQUEST['mode']) ? $_REQUEST['mode'] : null;
            $pdo = $this->dbConnect();
            $prm = [];
            switch ($mode) {
                case 'chk_nick':
                    $chk = !empty($_REQUEST['nickbaru']) ? $_REQUEST['nickbaru'] : null; 
                    if($chk){
                        $stm = $pdo->prepare("SELECT username FROM tbuser WHERE username=:nickbaru LIMIT 1");
                        $stm->execute(['nickbaru' => $chk]);
                        if($stm->rowCount() > 0){
                            $msgs='Nama login sudah digunakan!';
                        }else{
                            $msgs='Nama login baru tersedia!';
                            $type='success';
                        }
                    }                    
                break;

                case 'chk_pass':
                    $stm = $pdo->prepare("SELECT username FROM tbuser WHERE username=:nick AND password=:pass LIMIT 1");
                    $stm->execute(['nick' => $_REQUEST['nick'], 'pass' => $_REQUEST['pass']]);
                    if($stm->rowCount()>0){
                        $msgs='Password Lama benar!';
                        $type='success';
                    }else{
                        $msgs='Password Lama salah!';
                        $type='danger';
                    }
                break;
                
                case 'update':

    //                     mode
    // update
    // user_id
    // 1
    // nickname
    // kapuas
    // nickbaru
    // realname
    // usr_phone
    // usr_email
    // passwd1
    // passwd2
    // passwd3
                    $core = true;
                    foreach (['passwd1','passwd2','passwd3'] as $key => $value) {
                        if(empty($_REQUEST[$value]))
                            $core = false;
                    }
                    if(!empty($_REQUEST['nickbaru'])){
                        $core = true;
                        $form['username'] = $_REQUEST['nickbaru'];
                    }
                    if(!empty($_REQUEST['passwd3']))
                        $form['password'] = $_REQUEST['passwd3'];
                    // check jika ada format catatan kop atau nama pejabat
                    $chk = $pdo->select('tbuser[~catatan~]', ['id' => $_REQUEST['user_id']]);
                    $json = [];
                    if(!empty($chk[0]['catatan'])){
                        $json = json_decode($chk[0]['catatan'],true);
                    }
                    $catatan =json_encode([
                        $_REQUEST['realname'],
                        $_REQUEST['usr_phone'],
                        $_REQUEST['usr_email'],
                        // catatan kop
                        !empty($json[3]) ? $json[3] : '',
                        // catatan pejabat
                        !empty($json[4]) ? $json[4] : ''
                    ]);
                    $form['catatan'] = preg_replace('/[^0-9a-zA-Z]/','', $catatan) ? $catatan : '';
                    // jika core true maka ada variable restart
                    $stm = $pdo->update('tbuser',$form,[
                        'id' => $_REQUEST['user_id']
                    ]);
                    $msgs='Data gagal di update!';
                    if($stm){
                        $msgs='Data berhasil di update!';
                        $type='success';
                    }
                    $rslt=[
                        'aksi' => $core ? 'restart' : $_REQUEST['mode'],
                        'nick' => $_REQUEST['nickbaru']
                    ];

                    if($core){
                        unset($_SESSION['token']);
                    }

                break;
            }
            
        }
        $this->apiResponse(200,$rslt,[
            'message' => $msgs,
            'type' => $type
        ]);

    }

}
