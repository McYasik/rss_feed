<?php /* attend $item */ ?>

<div class="rss-item">
    <a class="title" href="<?= $item['link'] ?>" target="_blank" rel="noopener">
        <?= $item['title'] ?>
    </a>
<!--    <itemdesc class="tooltiptext">--><?php //= $item['description'] ?><!--</itemdesc>-->
    <span>
        <?= $item['pubDate'] ?>
    </span>
</div>
