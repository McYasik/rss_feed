<?php /* attend $feed */ ?>

<div class="feed">
    <h5><?php echo $feed['title']; ?></h5>
    <div class="feed-content">
        <?php foreach ($feed['items'] as $item): ?>
            <?php require $BASE . '/templates/item.php'; ?>
        <?php endforeach; ?>
    </div>
</div>
