<?php 
// footer.php
global $conn, $is_page_using_sidebar;

if (!isset($is_page_using_sidebar)) {
    $is_page_using_sidebar = true;
}
?>

    </div> </div> <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>

    <?php if ($is_page_using_sidebar): ?>
    <script>
        $(document).ready(function() {
            const sidebar = document.getElementById('sidebar');
            const mobileCollapseButton = document.getElementById('sidebarCollapseMobile');
            const content = document.querySelector('.content'); 

            // 1. تفعيل القوائم المنسدلة (Dropdowns)
            $('.sidebar .dropdown-toggle').on('click', function(e) {
                e.preventDefault(); 
                $(this).next('.collapse').collapse('toggle');
                $('.sidebar .collapse').not($(this).next('.collapse')).collapse('hide');
                
                $(this).toggleClass('active-dropdown');
                $('.sidebar .dropdown-toggle').not(this).removeClass('active-dropdown');
            });

            // 2. منطق فتح القائمة الفرعية عند إعادة التحميل
            $('.list-unstyled .collapse a.active').each(function() {
                var submenu = $(this).closest('.collapse');
                submenu.collapse('show');
                submenu.prev('.dropdown-toggle').addClass('active-dropdown');
            });
            
            // 3. منطق الجوال لإظهار/إخفاء القائمة عند النقر على الزر
            if (mobileCollapseButton && sidebar) {
                mobileCollapseButton.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
            
            // إخفاء القائمة عند النقر خارجها على الجوال 
            if (content && sidebar) {
                content.addEventListener('click', function() {
                    if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                });
            }
            
            // منع إخفاء القائمة عند النقر داخلها
            if (sidebar) {
                 sidebar.addEventListener('click', function(e) {
                     if (window.innerWidth <= 768) {
                        e.stopPropagation();
                     }
                 });
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>