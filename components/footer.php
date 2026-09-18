<footer class="pos-footer border-t border-slate-200 bg-white/80 px-4 py-4 sm:px-6 lg:px-8">
<div class="flex w-full flex-col items-center justify-between gap-2 text-[11px] text-slate-500 sm:flex-row">
<p>&copy; <?=date('Y')?> <?=e(APP_NAME)?>. POS Profesional.</p><div class="flex gap-3"><a href="<?=APP_URL?>/admin/index.php" class="hover:text-emerald-600">Dashboard</a><a href="<?=APP_URL?>/kasir/index.php" class="hover:text-emerald-600">Kasir</a></div>
</div>
</footer>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?=APP_URL?>/assets/js/dashboard.js?v=<?=filemtime(__DIR__.'/../assets/js/dashboard.js')?>"></script>
<script>
// Auto-submit form pencarian di admin & kasir
(function() {
    function initAutoSubmit() {
        var searchInputs = document.querySelectorAll('input[name="q"]');
        searchInputs.forEach(function(input) {
            if (input.dataset.autoSubmitBound) return;
            input.dataset.autoSubmitBound = '1';
            var debounceTimer = null;
            input.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                var form = this.closest('form');
                if (!form) return;
                var method = (form.getAttribute('method') || 'GET').toUpperCase();
                if (method !== 'GET') return;
                var val = this.value.trim();
                debounceTimer = setTimeout(function() {
                    // Cari halaman 1 jika ada pagination
                    var pageInput = form.querySelector('input[name="page"]');
                    if (pageInput) pageInput.value = '1';
                    form.submit();
                }, 400);
            });
        });

        // Juga auto submit saat filter dropdown kategori/tipe/status berubah
        var filterSelects = document.querySelectorAll('form select[name="cat"], form select[name="type"], form select[name="status"], form select[name="mod"], form select[name="act"]');
        filterSelects.forEach(function(sel) {
            if (sel.dataset.autoSubmitBound) return;
            sel.dataset.autoSubmitBound = '1';
            sel.addEventListener('change', function() {
                var form = this.closest('form');
                if (!form) return;
                var method = (form.getAttribute('method') || 'GET').toUpperCase();
                if (method !== 'GET') return;
                var pageInput = form.querySelector('input[name="page"]');
                if (pageInput) pageInput.value = '1';
                form.submit();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAutoSubmit);
    } else {
        initAutoSubmit();
    }
})();
</script>
<script>
// Konfigurasi SweetAlert2 gaya template dashboard & auto-interceptor hapus & logout
(function() {
    function initSweetAlerts() {
        if (!window.Swal) return;

        // Custom mixin untuk template styling
        window.posAlert = Swal.mixin({
            buttonsStyling: false,
            customClass: {
                confirmButton: 'swal-btn-confirm',
                cancelButton: 'swal-btn-cancel'
            }
        });

        // Intercept semua form hapus (act=delete / pay_del)
        document.querySelectorAll('form').forEach(function(form) {
            var actInput = form.querySelector('input[name="act"]');
            var isDelete = actInput && (actInput.value === 'delete' || actInput.value === 'pay_del');
            var hasConfirmSubmit = form.getAttribute('onsubmit') && form.getAttribute('onsubmit').indexOf('confirm') !== -1;

            if (isDelete || hasConfirmSubmit) {
                // Hapus onsubmit bawaan browser
                form.removeAttribute('onsubmit');
                form.onsubmit = null;

                if (form.dataset.swalDeleteBound) return;
                form.dataset.swalDeleteBound = '1';

                form.addEventListener('submit', function(e) {
                    if (form.dataset.swalConfirmed === 'true') {
                        form.dataset.swalConfirmed = 'false';
                        return true;
                    }
                    e.preventDefault();
                    window.posAlert.fire({
                        icon: 'warning',
                        title: 'Konfirmasi Hapus',
                        text: 'Data yang dihapus tidak dapat dikembalikan. Lanjutkan?',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus',
                        cancelButtonText: 'Batal',
                        customClass: {
                            confirmButton: 'swal-btn-confirm swal-btn-danger',
                            cancelButton: 'swal-btn-cancel'
                        }
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            form.dataset.swalConfirmed = 'true';
                            form.submit();
                        }
                    });
                });
            }
        });

        // Intercept link konfirmasi (misal sahkan opname)
        document.querySelectorAll('a[onclick*="confirm"]').forEach(function(link) {
            var onclickStr = link.getAttribute('onclick') || '';
            link.removeAttribute('onclick');
            link.onclick = null;
            if (link.dataset.swalLinkBound) return;
            link.dataset.swalLinkBound = '1';

            link.addEventListener('click', function(e) {
                e.preventDefault();
                var href = this.getAttribute('href');
                window.posAlert.fire({
                    icon: 'question',
                    title: 'Konfirmasi',
                    text: 'Sahkan stock opname? Stok sistem akan diperbarui sekarang.',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Sahkan',
                    cancelButtonText: 'Batal'
                }).then(function(result) {
                    if (result.isConfirmed && href) {
                        window.location.href = href;
                    }
                });
            });
        });

        // Intercept link logout
        document.querySelectorAll('a[href*="logout.php"]').forEach(function(link) {
            if (link.dataset.swalLogoutBound) return;
            link.dataset.swalLogoutBound = '1';
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var href = this.getAttribute('href');
                window.posAlert.fire({
                    icon: 'warning',
                    title: 'Konfirmasi Logout',
                    text: 'Apakah Anda yakin ingin keluar dari sistem?',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Keluar',
                    cancelButtonText: 'Batal'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        window.location.href = href;
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSweetAlerts);
    } else {
        initSweetAlerts();
    }
})();
</script>
<script>
<?php if($m=flash_get('success')): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil',
    text: <?=json_encode($m)?>,
    timer: 2000,
    timerProgressBar: true,
    showConfirmButton: false,
    buttonsStyling: false,
    customClass: {
        confirmButton: 'swal-btn-confirm'
    }
});
<?php endif; ?>
<?php if($m=flash_get('error')): ?>
Swal.fire({
    icon: 'error',
    title: 'Gagal',
    text: <?=json_encode($m)?>,
    buttonsStyling: false,
    customClass: {
        confirmButton: 'swal-btn-confirm'
    }
});
<?php endif; ?>
<?php if($m=flash_get('warning')): ?>
Swal.fire({
    icon: 'warning',
    title: 'Peringatan',
    text: <?=json_encode($m)?>,
    buttonsStyling: false,
    customClass: {
        confirmButton: 'swal-btn-confirm'
    }
});
<?php endif; ?>
</script>
