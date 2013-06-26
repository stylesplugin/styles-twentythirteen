This class is meant to be included in Styles child-plugins.

For the class to work, child plugins must have a WordPress plugin header with `Require` set, like this:

    /*
    Plugin Name: Styles: TwentyEleven
    Plugin URI: http://stylesplugin.com
    
    Require: Styles 1.0.7
    Styles Class: Styles_Child_Theme
    */    

The version number is optional.

The child-plugin must also include the class. For example:

    if ( !class_exists( 'Styles_Child_Notices' ) ) {
    	include dirname( __FILE__ ) . '/classes/styles-child-notices/styles-child-notices.php';
    }

Once installed `Styles_Child_Notices` offers these features:

* Check all plugins for "Require" header.
* If "Require" is set, display notices if the named plugin is not installed or activated.
* Provide links for installation from wordpress.org or activation in WordPress.
* Check and display notice for required version number is one is specified.
* Specifically for `Styles`, display notices in `customize.php` in addition to the WordPress Admin.