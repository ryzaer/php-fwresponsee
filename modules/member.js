App.pages.member = (function () {
  function init() {
    // init login member plugins/widget.min.js
    console.log('member loaded!');
    // tambah fungsi history cetak  
    $('#terakhir-cetak').initHistory();  
    // check status login
    $(window).assign();
    $('#panel-editor').show();
    $('#edit-pemohon').hide();
    $('#riwayat-register').hide();
    $('#form-pemohon input[type=radio].segment, #data-pemohon input[type=radio].segment').panelWrapper(),
    // aktifkan autocomplete list keperluan
    $.getJSON("keperluan", function(data) {
        !data || $('#keperluan').simpleAutocomplete({
            data: data.result,
            // onNotExistLabel: function(params) {
            //     var newId = params.latestId + 1;
            //     var newLabel = params.label;
            //     var dataArr = params.data;
            //     alert(newLabel + ' is not Exist! Menambah data ' + $(this).val());
            //     dataArr.push({
            //         id: newId,
            //         label: newLabel
            //     });
            //     // set data-id agar input tetap menyimpan id baru
            //     $(this).data("id", newId);
            //     // agar event memakai data yang sudah di update
            //     $(this).trigger('keyup');
            //     // hide suggestions
            //     $(this).siblings('.autocomplete-suggestions').hide();                        
            // }
        })
    });
    /** ini data editor */
    var cariPemohon = {
        ajax: function (page, callback) {
            let dataCari = $('.searching.cari-nik').val().length == 16 ? $('.searching.cari-nik').val() : $('.searching.cari-nama').val();
            $.post('data/pemohon', { page: page, cari: dataCari}, function (res) {
                callback(res); // kirim seluruh respons ke plugin
            }, 'json');
        },
        renderRow: function (row, index) {
            if(!row){
                // jika data kosong
                return `
                    <tr>
                        <td class="text-center" colspan="3">Data Tidak Ditemukan!</td>
                    </tr>
                `;
            }else{
                return `
                    <tr data-id=${row.id}>
                        <td class="text-center">${index + 1}</td>
                        <td style="width: 30%;"><b>${row.nama}</b><br><small>${row.ttl}</small></td>
                        <td><small>${row.alamat}</small></td>
                    </tr>
                `;
            }
        },
        onSuccess: function () {
            $(this).find('tbody tr').xbind('click', function () {
                let _this = this;
                $(_this).addClass('loading');
                $('.table-overlay').addClass('blocked');
                $.formBiodata('open',$(this).data('id'),function(res){
                    $(_this).removeClass('loading');
                    $('.table-overlay').removeClass('blocked');
                    if(res.status)
                        $.simpleAlert({title: `Error ${res.status}!`, message: 'Tidak dapat mengambil data', type: 'error',timeOut: 1500});
                })
            })
        }
    };
    $('#keperluan').on('keydown', function(e){
        if(e.key == 'Enter')
            $('#btnKeperluan').focus();
    });
    $('#tbskck').ajaxPagination(cariPemohon);
    $('#btnLoadUlang').on('click', function () {
        $('.searching').val('');  
        $('#tbskck').ajaxPagination('reload',cariPemohon);
        $('.searching.cari-nama').focus()
    });
    //manajemen tombol shortcut
    $(window).on('keydown', function(e) {
        if (e.altKey && (e.key === 'x' || e.key === 'X')) {
            // shortcut escape close form biodata
            if ($('.modal-box-wrapper').length > 0) {
                $.dynamicModal('close');
                return;
            }
            if ($('.simple-alert-overlay').length > 0) {
                $.simpleAlert('close');
                return;
            }
            if ($('#edit-pemohon').is(':visible')) {
                $.formBiodata('close');
                $('input.cari-nama').focus();
                return;
            }            
        }
        for (let i = 1; i <= 5; i++) {
            if (e.altKey && e.key === i.toString()) {
                if(!$('#edit-pemohon').is(':visible')){
                    $('#tbskck tbody tr:nth-child(' + i + ')').trigger('click');
                }else{
                    $('#tb-register tbody tr:nth-child(' + i + ') td:first-child').trigger('click');
                }
            }
        }
        if (e.altKey && (e.key === 'q' || e.key === 'Q')) {
            if(!$('#edit-pemohon').is(':visible')){
                $('input.cari-nik').focus();
            }else{
                $('input[name=nik]').focus();
            }
        }
        if (e.altKey && (e.key === 'w' || e.key === 'W')) {
            if(!$('#edit-pemohon').is(':visible')){
                $('input.cari-nama').focus();
            }else{
                $('#keperluan').focus();
            }
        }
        if (e.altKey && (e.key === 'c' || e.key === 'C')) {
            if($('.btn-copy').length > 0)
                $('.btn-copy').trigger('click');
        }
        if (e.altKey && (e.key === 's' || e.key === 'S')) {
            if($('#edit-pemohon').is(':visible'))
                $('#btnProses').trigger('click')
        }
        if (e.altKey && (e.key === 'n' || e.key === 'N')) {
            $.formBiodata('open');
            $('input[name=nik]').focus();
        }
        if (e.altKey && (e.key === 'r' || e.key === 'R')) {
            $('.searching').val('');  
            $('#tbskck').ajaxPagination('reload',cariPemohon);
            $('.searching.cari-nama').focus()
        }
        let step = 50; // jarak scroll dalam pixel
        if (e.altKey && e.key === 'ArrowDown') {
            window.scrollBy(0, step);
        }
        if (e.altKey && e.key === 'ArrowUp') {
            window.scrollBy(0, -step);
        }
    });
    $('.searching.cari-nik').on('keydown', function (e) {
        if(e.key == 'Enter'){  
            e.preventDefault();
            let __this = $(this);            
            if(__this.val().length == 16){
                $.post('biodata', { id: __this.val(), token: $.sessionHandler().get('token') }).done((response) => {
                    if(response.result.id > 0){
                        $.formBiodata('open'),    
                        $.formBiodata('pull',response.result, response.register),
                        $('#btnProses .text').text('Update Data'),
                        $('#keperluan').focus(),
                        __this.val('');                                              
                    }else{
                        $.simpleAlert({
                            title: 'NIK belum terdaftar!',
                            message: `NIK : <b>${__this.val()}</b><br>tidak ditemukan dalam database<br>Ingin buat data baru untuk NIK ini?`,
                            type: 'warning',
                            showYesNo: true,
                            btnYes: 'Ya, Buatkan Data Baru',
                            btnNo: 'Tidak',
                            onYes: function() {
                                // reset untuk data baru
                                $.formBiodata('reset');
                                // buka form
                                $.formBiodata('open');
                                $('input[name=nik]').val(__this.val());
                                $('input[name=nama]').focus();
                                __this.val('');
                            },
                            onNo: function() {
                                __this.val('').focus();
                            }
                        });
                        $('.simple-alert-box button.btn-success').focus();
                    }
                })
            }else{
                $.simpleAlert({
                    title: 'Terjadi Kesalahan!',
                    message: `NIK tidak valid atau tidak sesuai!`,
                    type: 'warning',
                    timeOut: 1500
                })
            }   
        }
    });
    $('.searching.cari-nama').on('keydown', function (e) {
        if(e.key == 'Enter'){            
            e.preventDefault();
            $('#tbskck').ajaxPagination('reload',cariPemohon);
        }
    });
    // Contoh penggunaan button ajax
    // $('#btnLogout').ajaxButton({
    //     url : "https://jsonplaceholder.typicode.com/posts",
    //     payload: { userId: 1, message: 'Hello' },
    //     onSuccess: function(res, btn) {
    //         alert(btn.data('alert'));
    //         console.log("Sukses dari tombol:", btn.text(), res);
    //     },
    //     onError: function(err, btn) {
    //         console.error("Gagal dari tombol:", btn.text(), err);
    //     }
    // });
    // Aktifkan semua simple select
    // $('.normal-select').simpleSelect();
    // setTimeout(function() {
    //     $('select[name=kel]').val(2).trigger('change');
    // }, 10);
    // Aktifkan simple select form select 
    $.formBiodata('form').select.forEach(k => {
        $.getJSON(`kategori/opt_${k}`, function (resp) {
            if(resp.result)
                resp.result.for_each(opt => {
                    $(`select[name=${k}]`).append(`<option value="${opt.gid}">${opt.item}</option>`)
                }).done(function() {
                    // Aktifkan simple select
                    $(`select[name=${k}]`).simpleSelect();
                })
        })
    });
    // Aktifkan simple select untuk kode area
    $.formBiodata('form').select_area.forEach(k => {
        $(`select[name=${k}]`).simpleSelect();
    });
    // penanggalan
    $('.datepicker').get().forEach(inputs => {
        flatpickr(inputs, {
            locale: "id",
            dateFormat: "d/m/Y",
            allowInput: true,
            disableMobile: true, 
            onOpen: function(selectedDates, dateStr, instance) {
                // navigasi sugestion tanggal dengan keyboard
                setTimeout(() => {
                 let selected = instance.calendarContainer.querySelector(".flatpickr-day.selected");
                     nowDate = instance.calendarContainer.querySelector(".flatpickr-day.today"); 
                 if (!selected) 
                     selected = nowDate
                 selected.focus();
                }, 10);
            },
            onClose: function(selectedDates, dateStr, instance) {
                // Jika tahun dibawah 1920, maka kosongkan input
                const date = instance.parseDate(dateStr, "d/m/Y");
                if (date && date.getFullYear() < 1920)
                    instance.clear()
            }
        })
    });
    // button proses
    $('#btnProses').on('click', function() {
        $(this).execBiodata();
    });

    // event ceklis show/hide catatan kriminal
    $('textarea[name=kriminal]').hide();
    $('input[name=chk-kriminal]').on('change', function() {
        if (this.checked)
            $('textarea[name=kriminal]').show();
        else
            $('textarea[name=kriminal]').hide();
    });
    // event open/close formBiodata
    $('.box-nav-icon, #btnExit').formBiodata('close');
    $('#btnTambahData').formBiodata('open');    
    // tooltip content
    $('.tooltip-content').xbind('click', function() {
        alert($(this).data('id'))
    });    
    // ubah data operator
    $('#data-operator').formOperator();
  }

  return {
    init: init,
    child:[],
    plugins: [
      'flatpickr',
      'ajaxPagination',
      'simplePaginate',
      'simpleAutoComplete',
      'member/formBiodata',
      'member/formOperator',     
      'member/execBiodata',     
      'member/historyCetak'     
    ]
  };
})();