<?php if($versions->have_posts()) : ?>
<ul>
	<?php while($versions->have_posts()) : $versions->the_post(); ?>
	<!-- need to figure out the best info to show here -->
		<li><a href="post.php?post=<?php the_ID(); ?>&amp;action=edit" target="_blank"><?php the_title(); ?></a> &mdash; <?php the_author(); ?></li>
	<?php endwhile;  ?>
</ul>
<?php endif; ?>
<a class="button" href="<?php echo esc_url($url); ?>">Create</a>
