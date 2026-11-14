<?php
/* @var mixed $id - The batch id */
?>
<div class="wrap">
    <div class="batch-single">
    	<?php WP_BP_Helper::render('batch-manage', ['id' => $id]); ?>
    </div>
</div>
<div class="wrap">
    <a href="admin.php?page=dg-batches">Back to List</a>
</div>