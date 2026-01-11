<?php $basePath = defined('BASE_PATH') ? BASE_PATH : ''; ?>

<div class="common-container">
    <?php if (!empty($data['title'])): ?>
        <h2 class="page-title"><?= htmlspecialchars($data['title']) ?></h2>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="status status-error" role="alert">
            <ul>
                <?php if (is_array($_SESSION['error'])): ?>
                    <?php foreach ($_SESSION['error'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li><?= htmlspecialchars($_SESSION['error']) ?></li>
                <?php endif; ?>
            </ul>
            <?php unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="buttons">
        <?php if (isset($_SESSION['user'])): ?>
            <?php if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin'): ?>
                <a href="<?= $basePath ?>/admin" class="btn btn-primary">Admin Panel</a>
                <a href="<?= $basePath ?>/room" class="btn btn-secondary">Go to Room</a>
                <?php else: ?>
                <a href="<?= $basePath ?>/room" class="btn btn-primary">Go to Room</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="<?= $basePath ?>/login" class="btn btn-primary">Login</a>
            <a href="<?= $basePath ?>/register" class="btn btn-secondary">Register</a>
        <?php endif; ?>
    </div>
</div>