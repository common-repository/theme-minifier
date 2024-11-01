<?php
/*
Plugin Name: Theme Minifier
Plugin URI: http://squidesma.com/blog/wordpress-theme-minifier/
Description: Minifies any theme with one click, reducing bandwidth and lowering page loading times
Version: 2.0
Author: Squidesma
Author URI: http://squidesma.com/
License: GPL2

---------------------------------------------------------------------------
Copyright 2010 Squidesma (http://squidesma.com/)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
add_action( 'admin_menu', 'theme_minifier_menu' );
function theme_minifier_menu()
{
	add_options_page( 'Theme Minifier', 'Theme Minifier',  'administrator', __FILE__, 'theme_minifier_options' );
}
function theme_minifier_options()
{
	echo '<div class="wrap">';
	echo '<div id="icon-themes" class="icon32"></div>';
	echo '<h2>Theme Minifier</h2>';
	if ( isset( $_POST['theme_to_minify'] ) )
		minify_it( $_POST['theme_to_minify'], $_POST['css_tidy'], $_POST['js_method'], $_POST['img_pct'] );
	else
	{
		echo '<form action="#" method="post">';
		echo '<table class="form-table">';
		echo '  <tr>';
		echo '    <th><label for="theme_to_minify">Theme</label></th>';
		echo '    <td><select id="theme_to_minify" name="theme_to_minify">';
		$themes = (array) get_themes();
		foreach ( $themes as $theme )
		{
			echo '<option value="' . $theme['Template'] . '"';
			if ( $theme['Template'] === $_POST['theme_to_minify'] )
				echo ' selected';
			echo '>' . $theme['Name'] . '</option>';
		}
		echo '    </select></td>';
		echo '  </tr>';
		echo '  <tr>';
		echo '    <th><label for="img_pct">Image Compression Percent</label></th>';
		echo '    <td>';
		echo '      <select id="img_pct" name="img_pct">';
		echo '        <option value="100">100 (no compression)</option>';
		echo '        <option value="90">90</option>';
		echo '        <option value="80">80</option>';
		echo '        <option value="70">70</option>';
		echo '        <option value="60">60</option>';
		echo '        <option value="50">50</option>';
		echo '        <option value="40">40</option>';
		echo '        <option value="30">30</option>';
		echo '        <option value="20">20</option>';
		echo '        <option value="10">10</option>';
		echo '      </select>';
		echo '      <span class="description">Select percentage of the original image quality. NOTE: This currently on works for PNGs and JPGs.</span>';
		echo '    </td>';
		echo '  </tr>';
		echo '  <tr>';
		echo '    <th><label for="css_tidy">Use CSSTidy?</label></th>';
		echo '    <td>';
		echo '      <input type="checkbox" name="css_tidy"/>';
		echo '      <span class="description"><a href="http://csstidy.sourceforge.net/" target="_blank">CSSTidy</a> optimises CSS, resulting in smaller files, but may break CSS3.</span>';
		echo '    </td>';
		echo '  </tr>';
		echo '  <tr>';
		echo '    <th><label for="js_method">JS Compression Method</label></th>';
		echo '    <td>';
		echo '      <select id="js_method" name="js_method">';
		echo '        <option value="jsmin">JSMin</option>';
		echo '        <option value="packer">Packer</option>';
		echo '      </select>';
		echo '      <span class="description"><a href="http://joliclic.free.fr/php/javascript-packer/en/" target="_blank">Packer</a> uses eval to compress JavaScript, resulting in smaller files, but is <a href="http://blogs.msdn.com/b/ericlippert/archive/2003/11/01/53329.aspx">often considered evil</a>.</span>';
		echo '    </td>';
		echo '  </tr>';
		echo '  <tr><th><input class="button-primary" type="submit" value="Minify It!"/></th><td></td></tr>';
		echo '</table>';
		echo '</form>';
	}
	echo '</div>';
}
function minify_it( $theme, $css_tidy, $js_method, $img_pct )
{
	echo '<ol>';
	$root = get_theme_root();
	$src = "$root/$theme";
	$dst = "$root/min-$theme";
	$theme_data = get_theme_data( $src.'/style.css' );
	$theme_name = $theme_data['Title'];


	echo '<li>Copying Existing Theme</li>';
	recurse_copy($src,$dst);

	echo '<li>Updating Screenshot</li>';
	$watermark = imagecreatefrompng( WP_PLUGIN_DIR.'/theme-minifier/watermark.png' );
	$watermark_width = imagesx( $watermark );
	$watermark_height = imagesy( $watermark );
	$image = imagecreatefrompng( "$root/min-$theme/screenshot.png" );
	imagecopymerge( $image, $watermark, 0, 0, 0, 0, $watermark_width, $watermark_height, 50 );
	imagepng( $image, "$root/min-$theme/screenshot.png" );
	imagedestroy( $image );
	imagedestroy( $watermark );

	echo '<li>Minifying Files';
	echo '<table class="widefat" style="width:55%;margin-top:5px;">';
	echo '<thead><tr><th>File</th><th>Orig Size</th><th>New Size</th><th>Savings</th><th>New Ext</th><th>New Ext<br>Size</th><th>New Ext<br>Savings</th></tr></thead>';
	echo '<tbody>';
	$files = recurse_list( "$root/min-$theme" );
	$cnt = 0;
	$tidy = isset( $css_tidy );
	$pack_js = ( $js_method === 'packer' );
	$images = array();
	$jpg_pct = (int) $img_pct;
	$png_pct = 10 - ($img_pct / 10);

	for ( $i = 0; $i <= count($files); $i++ )
	{
		$file = $files[$i];
		$path_info = pathinfo($file);
		if ( $file !== '.' && $file !== '..' )
		{
			$filename = $file;
			$nice_filename = str_replace( "$root/min-$theme/", '', $filename );
			$ext = $path_info['extension'];
			$old = null;
			$new_img_ext = null;
			if ( $ext === 'css' || $ext === 'js' || $ext === 'php' )
			{
				$file_contents = file_get_contents( $filename );
				$old = strlen($file_contents);
				if ( $ext === 'css' )
					$file_contents = compress_css( $file_contents, ( $nice_filename === 'style.css' ), $tidy );
				else if ( $ext === 'js' )
					$file_contents = compress_js( $file_contents, $pack_js );
				else if ( $ext === 'php' )
					$file_contents = compress_php( $file_contents );
				$new = strlen($file_contents);
				$fh = fopen( $filename, 'w' ) or die( "Can't Open File" );
				fwrite( $fh, $file_contents );
				fclose( $fh );
				$img_new = $new;
			}
			else if ( $ext === 'gif' || $ext === 'jpg' || ( $ext === 'png' && $nice_filename !== 'screenshot.png' ) )
			{
				$old = filesize( $filename );
				$temp_filename = $filename.'.tmp';
				if ( $ext === 'gif' )
					$img = imagecreatefromgif( $filename );
				else if ( $ext === 'jpg' )
					$img = imagecreatefromjpeg( $filename );
				else if ( $ext === 'png' )
					$img = imagecreatefrompng( $filename );
				imagegif( $img, "$filename.gif" );
				imagejpeg( $img, "$filename.jpg", $jpg_pct );
				imagepng( $img, "$filename.png", $png_pct );
				imagedestroy( $img );

				$gif_size = filesize( "$filename.gif" );
				$jpg_size = filesize( "$filename.jpg" );
				$png_size = filesize( "$filename.png" );

				$new_ext = null;
				$new = $old;
				if ( $gif_size < $new )
				{
					$new_ext = 'gif';
					$new = $gif_size;
				}
				if ( $jpg_size < $new )
				{
					$new_ext = 'jpg';
					$new = $jpg_size;
				}
				if ( $png_size < $new )
				{
					$new_ext = 'png';
					$new = $png_size;
				}

				if ( $new_ext )
				{
					if ( $new_ext !== $ext )
					{
						$new_img_ext = $new_ext;
						$img_new = $new;
						if ( $ext === 'gif' )
							$new = $gif_size;
						else if ( $ext === 'jpg' )
							$new = $jpg_size;
						else if ( $ext === 'png' )
							$new = $png_size;
						if ( $new > $old )
							$new = $old;
					}
					else
						rename( "$filename.$ext", $filename );//best as same type
					if ( $new_ext === 'gif' )
					{
						unlink( "$filename.jpg" );
						unlink( "$filename.png" );
					}
					else if ( $new_ext === 'jpg' )
					{
						unlink( "$filename.gif" );
						unlink( "$filename.png" );
					}
					else if ( $new_ext === 'png' )
					{
						unlink( "$filename.gif" );
						unlink( "$filename.jpg" );
					}
					$new_img = $new;
				}
				else
				{
					$img_new = $old;
					unlink( "$filename.gif" );
					unlink( "$filename.jpg" );
					unlink( "$filename.png" );
				}
			}
			if ( $old !== null )
			{
				$savings = $old - $new;
				$img_savings = $old - $img_new;
				if ( ++$cnt % 2 === 0 )
					$class = ' class="alternate"';
				else
					$class = '';
				if ( $new_img_ext !== null )
					echo "<tr$class><td>$nice_filename</td><td>$old</td><td>$new</td><td>$savings</td><td>$new_img_ext*</td><td>$img_new*</td><td>$img_savings*</td></tr>";
				else
					echo "<tr$class><td>$nice_filename</td><td>$old</td><td>$new</td><td>$savings</td><td></td><td></td><td></td></tr>";
				$tot_old += $old;
				$tot_new += $new;
				$tot_img_new += $img_new;
			}
		}
	}
	$tot_savings = $tot_old - $tot_new;
	$tot_img_savings = $tot_old - $tot_img_new;
	echo '</tbody>';
	echo '<tfoot>';
	echo '<tr>';
	echo ' <th>Total</th>';
	echo ' <th>' . format_filesize( $tot_old ) . '</th>';
	echo ' <th>' . format_filesize( $tot_new ) . '</th>';
	echo ' <th>' . format_filesize( $tot_savings ) . '<br>(' . number_format( 100.0 - ( $tot_new / $tot_old * 100 ), 2, '.', ',' ) . '%)</th>';
	echo ' <th>----</th>';
	if ( $tot_img_new > 0 )
	{
		echo ' <th>' . format_filesize( $tot_img_new ) . '*</th>';
		echo ' <th>' . format_filesize( $tot_img_savings ) . '*<br>(' . number_format( 100.0 - ( $tot_img_new / $tot_old * 100 ), 2, '.', ',' ) . '%)</th>';
	}
	else
	{
		echo ' <th>----</th>';
		echo ' <th>----</th>';
	}
	echo '</tr>';
	echo '</tfoot>';
	echo '</table>';
	echo '</li>';

	echo '</ol>';
	echo '<p>Theme "<strong>' . $theme_name. '</strong>" minified successfully. <a href="options-general.php?page=theme-minifier/index.php">Minify another theme</a>.</p>';
}

/** COMPRESS METHODS **/
function compress_css( $css, $is_main, $tidy )
{
	if ( $is_main )
	{
		preg_match( '/Theme Name:(.*)/i', $css, $matches );
		$theme_name = $matches[0];
	}

	if ( $tidy )
	{
		require_once 'csstidy-1.3/class.csstidy.php';
		$tidy = new csstidy();
		$tidy->parse( $css );
		$css = $tidy->print->plain();
	}

	$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );//remove comments
	$css = preg_replace( '/[\t|\n|\r]/', '', $css );//remove tab, new line, and carriage returns
	$css = preg_replace( '/\s+/', ' ', $css );//remove multiple spaces
	$css = preg_replace( '/ {/', '{', $css );//remove space before opening brace
	$css = preg_replace( '/;}/', '}', $css );//remove last semicolon before closing brace
	$css = preg_replace( '/: /', ':', $css );//remove space between attribute and value
	$css = preg_replace( '/, /', ',', $css );//remove space after commas

	if ( $is_main )
		$css = "/*$theme_name-MIN*/$css";
	return $css;
}
function compress_js( $js, $pack )
{
	if ($pack)
	{
		require_once 'packer.php-1.1/class.JavaScriptPacker.php';
		$packer = new JavaScriptPacker( $js );
		$js = $packer->pack();
	}
	else
	{
		require_once 'jsmin/jsmin.php';
		$js = JSMin::minify( $js );
	}
	return $js;
}
function compress_php( $php )
{
	$php = preg_replace( '/<!--.*?-->/', '', $php );//remove comments
	$php = preg_replace( '/\t/', '', $php );//remove tabs
	$php = preg_replace( '/^\s\s+/m', '', $php );//remove all leading spaces
	$php = preg_replace( '/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/', '', $php );//remove blank lines
	$php = preg_replace( '/>[\s+|\n|\r]</', '><', $php );//remove spaces, new line, and carriage returns between tags
	$php = preg_replace( '/ \/>/', '/>', $php );//remove spaces before self-closing tags
	return $php;
}
/** END COMPRESS METHODS **/

/** FILE UTILITIES (List/Copy/Delete) **/
function recurse_list( $src )
{
	$array_items = array();
	$handle = opendir( $src );
	if ( $handle )
	{
		while ( false !== ( $file = readdir( $handle ) ) )
		{
			if ( $file !== '.' && $file !== '..' )
			{
				if ( is_dir( "$src/$file" ) )
				{
					$array_items = array_merge( $array_items, recurse_list( "$src/$file" ) );
					$array_items[] = preg_replace( "/\/\//si", "/", "$src/$file" );
				}
				else
					$array_items[] = preg_replace( "/\/\//si", "/", "$src/$file" );
			}
		}
		closedir( $handle );
	}
	return $array_items;
}
function recurse_copy( $src, $dst )
{
	$dir = opendir( $src );
	if ( file_exists( $dst ) )
		recurse_del( $dst );
	mkdir( $dst, 0755 );
	while ( ( $file = readdir( $dir ) ) )
	{
		if ( $file !== '.' && $file !== '..' )
		{
			if ( is_dir( "$src/$file" ) )
				recurse_copy( "$src/$file", "$dst/$file" );
			else
				copy( "$src/$file", "$dst/$file" );
		}
	}
	closedir( $dir );
}
function recurse_del( $src )
{
	if ( is_dir( $src ) )
		$dir = opendir( $src );
	if ( !$dir )
		return false;
	while ( $file = readdir( $dir ) )
	{
		if ( $file !== '.' && $file !== '..' )
		{
			if ( is_dir( "$src/$file" ) )
				recurse_del( "$src/$file" );
			else
				unlink( "$src/$file" );
		}
	}
	closedir( $dir );
	rmdir( $src );
	return true;
}
/** END FILE UTILITIES **/

/** FORMATTING METHODS **/
function format_filesize( $bytes )
{
	$bytes = (float) $bytes;
	if ( $bytes < 1024 )
		return number_format( $bytes, 0, '.', ',' ) . ' bytes';
	else if ( $bytes < 1048576 )
		return number_format( $bytes / 1024, 2, '.', ',' ) .' KB';
	else if ( $bytes >= 1048576 )
		return number_format( $bytes / 1048576, 2, '.', ',' ) .' MB';
}
/** END FORMATTING METHODS **/
?>
