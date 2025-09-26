<?php

class HomeController extends \Router
{
    function index($slug)
    {
        $this->set('title',$this->get('pwa.name'));
        $this->set('time_script',strtotime('now'));
        $this->set('base_script',$this->http->base);
        print $this->render('templates/tailwind.html');
        // print $this->render('templates/home.html');
    }
    
    function member($slug)
    {        
        $this->set('title',"Member ~ ".$this->get('pwa.name'));
        $this->set('time_script',strtotime('now'));
        $this->set('base_script',$this->http->base);
        $shortkey[] = "<td>Kombinasi</td><td>&nbsp;</td><td>Keterangan</td>";
        foreach ([
            "Alt + Q" => "Mengalihkan cursor ke input Cari Nomor KTP/KTP atau ke Formulir input NIK/KTP",
            "Alt + W" => "Mengalihkan cursor ke input Cari Data Pemohon atau ke Pencarian Data Keperluan",
            "Alt + X" => "Menutup Formulir Biodata atau Popup Print SKCK",
            "Alt + R" => "Merefresh/reload pencarian biodata",
            "Alt + C" => "Meng-Copy Format Print SKCK",
            "Alt + N" => "Membuka Formulir baru SKCK, untuk mengentri biodata baru",
            "Alt + S" => "Eksekusi Formulir biodata Baru/Maupun perbaikan",
            "Alt + {1-5}" => "Membuka data pemohon atau membuka Popup format print SKCK berdasarkan nomor urut table",
            "Alt + Up" => "Scroll halaman keatas",
            "Alt + Down" => "Scroll halaman kebawah",
            "Up/Down/Left/Right" => "Popup penanggalan : navigasi tanggal",
            "Ctrl + {Up/Down}" => "Popup penanggalan : navigasi tahun",
            "Ctrl + {Left/Right}" => "Popup penanggalan : navigasi bulan",
        ] as $key => $value) {
         $shortkey[] = "<td>$key</td><td>:</td><td>$value</td>";
        }
        $this->set('shortkeys', $shortkey);
        // check session       
        if(!empty($_SESSION['token'])){
            $check = json_decode(\Crypto\ssl::decrypt($_SESSION['token']) ?? '[]',true);
            // JIka Role/level adalah 3 : Operator atau lebih tinggi
            // belum dikasi limit waktu
            if($check['role'] >= 3){                
                $this->set('login_role',$check['role']);
                // attach laman member
                print $this->render('templates/member.html');
            }
        }
        
    }


    function test($slug)
    {
        print json_encode($_POST);
    }

}