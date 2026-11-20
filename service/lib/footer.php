</main>

<?php include __DIR__.'/site_footer.php'; ?>

<?php if (!empty($showMemberTabs)) {
    $leaveKorea = !isset($pdo) || ksers_member_is_korea($pdo, isset($_SESSION['seq']) ? (int)$_SESSION['seq'] : 0);
?>
<div class="leave-modal" id="leaveModal" hidden>
    <div class="leave-modal-card" role="dialog" aria-modal="true" aria-labelledby="leaveModalMessage">
        <div class="leave-modal-icon" aria-hidden="true">!</div>
        <p class="leave-modal-text" id="leaveModalMessage"></p>
        <div class="leave-modal-actions">
            <button type="button" class="btn btn-outline-secondary" id="leaveModalGo"><?= $leaveKorea ? '이동하기' : 'Leave' ?></button>
            <button type="button" class="btn btn-primary-custom" id="leaveModalStay"><?= $leaveKorea ? '계속 진행' : 'Stay' ?></button>
        </div>
    </div>
</div>
<script>
window.ksersLeave = <?= json_encode(['korea' => $leaveKorea, 'unpaid' => !empty($ksersLeaveUnpaid)], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/service/ksers-leave.js"></script>
<?php } ?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>