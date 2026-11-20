</main> <!-- 메인 콘텐츠 영역 종료 -->
    </div>
</div>

<!-- Bootstrap 5 & Chart.js JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php
if (!empty($admin_page_script) && is_file($admin_page_script)) {
    include $admin_page_script;
}
?>

</body>
</html>