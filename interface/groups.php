<div class="wrap">
	<?php screen_icon(); ?>
	<h2>Edit Groups</h2>

	<?php if($group_list->have_groups()): ?>
	<ul>
		<?php while($group_list->have_groups()): $group = $group_list->the_group(); ?>
		<li><?php echo $group->get_name(); ?></li>
		<?php endwhile; ?>
	</ul>
	<?php endif; ?>
</div>
