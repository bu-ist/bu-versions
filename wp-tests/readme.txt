Running these tests requires the WordPress Test Suite
<http://svn.automattic.com/wordpress-tests/>. For more information, see
<http://codex.wordpress.org/Automated_Testing>.

To run these tests, you need to:

-- need to explain how/where to get PHPunit

1) Install the plugin in wp-plugins
2) If the plugin comprises of more than a single file, the main file needs to be symlinked
from the plugin directory to root of wp-plugins.
3) Symlink the wp-tests directory into wp-testcase.


@todo

Improvements to the WordPress Test Suite

* Load plugins properly
* Automatically discover test cases (perhaps through some magic).
