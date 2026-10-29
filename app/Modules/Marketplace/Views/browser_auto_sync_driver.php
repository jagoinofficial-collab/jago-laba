<?php if(!empty($_SESSION['user_id'])&&Workspace::can(Workspace::MARKETPLACE)): ?>
<script src="assets/marketplace/js/marketplace-browser-pulse.js?v=20261027" data-csrf="<?= htmlspecialchars(Workspace::csrf(),ENT_QUOTES,'UTF-8') ?>" defer></script>
<?php endif; ?>
