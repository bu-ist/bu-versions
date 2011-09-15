<?php if($versions->have_posts()) : ?>
<ul>
	<?php while($versions->have_posts()) : $versions->the_post(); ?>
	<!-- need to figure out the best info to show here -->
	<li><?php the_title(); ?></li>
	<?php endwhile;  ?>
</ul>
<?php endif; ?>
<a href="<?php echo esc_url($url); ?>">Create</a>