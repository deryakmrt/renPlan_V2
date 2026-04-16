<?php
/**
 * @var int $total_pages
 * @var int $page
 */
if ($total_pages > 1): 
  $qs = $_GET;
  unset($qs['page']);
  $base = 'orders.php' . (empty($qs) ? '' : '?' . http_build_query($qs));
  $start = max(1, $page - 3); 
  $end = min($total_pages, $page + 3);
  $prev_link = __orders_page_link($page - 1, $base);
  $next_link = __orders_page_link($page + 1, $base);
  $first_link = __orders_page_link(1, $base);
  $last_link = __orders_page_link($total_pages, $base);
?>
  <div class="pager-container">
    <div class="pager">
      <?php if ($page > 1): ?>
        <a class="pager-btn" href="<?= h($first_link) ?>">&laquo; İlk</a>
        <a class="pager-btn" href="<?= h($prev_link) ?>">&lsaquo; Geri</a>
      <?php endif; ?>

      <?php for ($i = $start; $i <= $end; $i++): 
        $lnk = __orders_page_link($i, $base);
        $is_curr = ((int)$i === (int)$page);
      ?>
        <a class="pager-page <?= $is_curr ? 'active' : '' ?>" href="<?= h($lnk) ?>"><?= $i ?></a>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a class="pager-btn" href="<?= h($next_link) ?>">İleri &rsaquo;</a>
        <a class="pager-btn" href="<?= h($last_link) ?>">Son &raquo;</a>
      <?php endif; ?>
    </div>

    <form method="get" class="pager-goto">
      <label>Sayfa:</label>
      <input type="number" name="page" value="<?= (int)$page ?>" min="1" max="<?= (int)$total_pages ?>">
      <?php foreach ($_GET as $gk => $gv): if ($gk !== 'page'): ?>
        <input type="hidden" name="<?= h($gk) ?>" value="<?= h($gv) ?>">
      <?php endif; endforeach; ?>
      <button type="submit" class="btn btn-secondary btn-sm">Git</button>
    </form>
  </div>
<?php endif; ?>