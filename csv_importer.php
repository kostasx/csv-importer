<?php
/*
Plugin Name: CSV Importer
Description: Import data as posts from a CSV file. <em>You can reach the author at <a href="mailto:d.v.kobozev@gmail.com">d.v.kobozev@gmail.com</a></em>.
Version: 0.3.10
Author: Denis Kobozev, Bryan Headrick, KostasX
*/
/**
 * LICENSE: The MIT License {{{
 *
 * Copyright (c) <2009> <Denis Kobozev>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author    Denis Kobozev <d.v.kobozev@gmail.com>
 * @copyright 2009 Denis Kobozev
 * @license   The MIT License
 * }}}
 */

class CSVImporterPlugin {

    var $defaults = array(
        'csv_post_title'      => null,
        'csv_post_post'       => null,
        'csv_post_type'       => null,
        'csv_post_excerpt'    => null,
        'csv_post_date'       => null,
        'csv_post_tags'       => null,
        'csv_post_categories' => null,
        'csv_post_author'     => null,
        'csv_post_slug'       => null,
        'csv_post_parent'     => 0,
    );

    var $log = array();

    function __construct(){
        add_action( 'admin_menu', array( $this, 'csv_admin_menu') );
        add_action( 'admin_init', array( $this, 'csv_register_settings' ) );
    }


    function csv_admin_menu() {
        require_once ABSPATH . '/wp-admin/admin.php';
        add_management_page( 'edit.php', 'CSV Importer', 'manage_options', __FILE__, array( $this, 'form' ) );
    }

    function csv_register_settings() {

        add_option( 'csv_importer_plugin_delimiter', ';' );
        add_option( 'csv_importer_plugin_eol', '\n' );

        register_setting( 'default', 'csv_importer_plugin_delimiter' ); 
        register_setting( 'default', 'csv_importer_plugin_eol' ); 

    } 

    /**
     * Determine value of option $name from database, $default value or $params,
     * save it to the db if needed and return it.
     *
     * @param string $name
     * @param mixed  $default
     * @param array  $params
     * @return string
     */
    function process_option($name, $default, $params) {
        if (array_key_exists($name, $params)) {
            $value = stripslashes($params[$name]);
        } elseif (array_key_exists('_'.$name, $params)) {
            // unchecked checkbox value
            $value = stripslashes($params['_'.$name]);
        } else {
            $value = null;
        }
        $stored_value = get_option($name);
        if ($value == null) {
            if ($stored_value === false) {
                if (is_callable($default) &&
                    method_exists($default[0], $default[1])) {
                    $value = call_user_func($default);
                } else {
                    $value = $default;
                }
                add_option($name, $value);
            } else {
                $value = $stored_value;
            }
        } else {
            if ($stored_value === false) {
                add_option($name, $value);
            } elseif ($stored_value != $value) {
                update_option($name, $value);
            }
        }
        return $value;
    }

    /**
     * Plugin's interface
     *
     * @return void
     */
    function form() {
        $opt_draft = $this->process_option('csv_importer_import_as_draft',
            'publish', $_POST);
        $opt_cat = $this->process_option('csv_importer_cat', 0, $_POST);

        if ('POST' == $_SERVER['REQUEST_METHOD']) {
            $this->post(compact('opt_draft', 'opt_cat'));
        }

        // form HTML {{{
?>

<div class="wrap">
    <h2>Import CSV</h2>
    <form class="add:the-list: validate" method="post" enctype="multipart/form-data">
        <!-- Import as draft -->
        <p>
        <input name="_csv_importer_import_as_draft" type="hidden" value="publish" />
        <label><input name="csv_importer_import_as_draft" type="checkbox" <?php if ('draft' == $opt_draft) { echo 'checked="checked"'; } ?> value="draft" /> Import posts as drafts</label>
        </p>
        <!-- File input -->
        <p><label for="csv_import">Upload file:</label><br/>
            <input name="csv_import" id="csv_import" type="file" value="" aria-required="true" /></p>
        <p class="submit"><input type="submit" class="button" name="submit" value="Import" /></p>
    </form>
    <h2>Standard Fields</h2>
	<strong>csv_post_title</strong> | csv_post_post | csv_post_type | csv_post_excerpt | csv_post_date | csv_post_tags | csv_post_categories | csv_post_author | csv_post_slug | csv_post_parent

    <div class="wrap">
        <?php screen_icon(); ?>
        <h2>Settings</h2>
        <form method="post" action="options.php"> 
            <?php settings_fields( 'default' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="csv_importer_plugin_delimiter">CSV Delimiter</label></th>
                        <td><input type="text" id="csv_importer_plugin_delimiter" name="csv_importer_plugin_delimiter" value="<?php echo get_option('csv_importer_plugin_delimiter'); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="csv_importer_plugin_eol">CSV End of Line</label></th>
                        <td><input type="text" id="csv_importer_plugin_eol" name="csv_importer_plugin_eol" value="<?php echo get_option('csv_importer_plugin_eol'); ?>" /></td>
                    </tr>
                </table>
            <?php submit_button(); ?>
        </form>
    </div>

<!-- Parent category -->      
<!--
<p>Organize into category <?php wp_dropdown_categories(array('show_option_all' => 'Select one ...', 'hide_empty' => 0, 'hierarchical' => 1, 'show_count' => 0, 'name' => 'csv_importer_cat', 'orderby' => 'name', 'selected' => $opt_cat));?><br/>      
<small>This will create new categories inside the category parent you choose.</small></p> 
-->

<h2>Custom taxonomies</h2>
<p>Once custom taxonomies are set up in your theme&#8217;s functions.php file or</p>
<p>by using a 3rd party plugin, `<strong>csv_ctax_(taxonomy name)</strong>` columns can be<br />
used to assign imported data to the taxonomies.</p>
<h3>__Non-hierarchical taxonomies__</h3>
<p>The syntax for non-hierarchical taxonomies is straightforward and is essentially<br />
the same as the `csv_post_tags` syntax.</p>
<h3>__Hierarchical taxonomies__</h3>
<p>The syntax for hierarchical taxonomies is more complicated. Each hierarchical<br />
taxonomy field is a tiny two-column CSV file, where _the order of columns<br />
matters_. The first column contains the name of the parent term and the second<br />
column contains the name of the child term. Top level terms have to be preceded<br />
either by an empty string or a 0 (zero). (precede each value with a comma).</p>

<h2>Featured Image</h2>
<p>You can attach an already uploaded image by using the '_csv_attached_file' CSV field.<br />
See example.csv.</p> 
   
</p>

<h2>Attachments</h2>
<p>You can now add attachments by uploading the files via ftp and then including<br />
the full URL to the attachment file including images, documents or any other file type<br />
that WordPress supports. The format is <strong>csv_attachment_(attachment name)</strong>.</p>
<p>Also, if the column name is csv_attachment_thumbnail, then the attachment will be set as
the post&#8217;s featured image.</p>

<h2>Custom/Meta Fields</h2>
<p>All columns not beginning with <strong>csv_</strong> will be imported as postmeta</p>

<h2>Serialized Data Support</h2>
<p>Now supports serializing data. Format meta field as follows:</p>
<p><strong>    key::value </strong></p>
<p>or</p>
<p><strong>    key::value[]key::value</strong>...
    
</p>
</div><!-- end wrap -->

<?php
        // end form HTML }}}

    }

    function print_messages() {
        if (!empty($this->log)) {

        // messages HTML {{{
?>

<div class="wrap">
    <?php if (!empty($this->log['error'])): ?>
    <div class="error">
        <?php foreach ($this->log['error'] as $error): ?>
            <p><?php echo $error; ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($this->log['notice'])): ?>
    <div class="updated fade">
        <?php foreach ($this->log['notice'] as $notice): ?>
            <p><?php echo $notice; ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div><!-- end wrap -->

<?php
        // end messages HTML }}}

            $this->log = array();
        }
    }

    /**
     * Handle POST submission
     *
     * @param array $options
     * @return void
     */
    function post( $options ) {
        if ( empty( $_FILES['csv_import']['tmp_name']) ) {
            $this->log['error'][] = 'No file uploaded, aborting.';
            $this->print_messages();
            return;
        }

        /* Version: 0.3.9
         if (!current_user_can('publish_pages') || !current_user_can('publish_posts')) {        
            $this->log['error'][] = 'You don\'t have the permissions to publish posts and pages. Please contact the blog\'s administrator.';        
            $this->print_messages();        
            return;         
         } 
         */

        require_once 'libs/DataSource.php';

        $time_start = microtime(true);

        $csv = new File_CSV_DataSource;
        $csv->settings['delimiter'] = get_option('csv_importer_plugin_delimiter', ';' );
        $csv->settings['eol']       = get_option('csv_importer_plugin_eol', '\n' );

        $file = $_FILES['csv_import']['tmp_name'];
        $this->stripBOM($file);

        if (!$csv->load($file)) {
            $this->log['error'][] = 'Failed to load file, aborting.';
            $this->print_messages();
            return;
        }

        // PAD SHORTER ROWS WITH EMPTY VALUES
        $csv->symmetrize();

        // WordPress sets the correct timezone for date functions somewhere in the bowels of wp_insert_post().
        // We need strtotime() to return correct time before the call to wp_insert_post().
        $tz = get_option('timezone_string');
        if ($tz && function_exists('date_default_timezone_set')) {
            date_default_timezone_set($tz);
        }

        $skipped  = 0;
        $imported = 0;
        $comments = 0;
        
        foreach ( $csv->connect() as $csv_data ) {
            if ( $post_id = $this->create_post( $csv_data, $options )) {
                $imported++;
                $comments += $this->add_comments( $post_id, $csv_data );
                             $this->create_custom_fields( $post_id, $csv_data );
                             $this->add_attachments( $post_id, $csv_data ); // @since 0.3.9
            } else {
                $skipped++;
            }
        }

        if (file_exists($file)) {
            @unlink($file);
        }

        $exec_time = microtime(true) - $time_start;

        if ($skipped) {
            $this->log['notice'][] = "<b>Skipped {$skipped} posts (most likely due to empty title, body and excerpt).</b>";
        }
        $this->log['notice'][] = sprintf("<b>Imported {$imported} posts and {$comments} comments in %.2f seconds.</b>", $exec_time);
        $this->print_messages();
    }

    function create_post( $data, $options ) {
        /* Version: 0.3.8
        $opt_draft = isset($options['opt_draft']) ? $options['opt_draft'] : null;   
        $opt_cat = isset($options['opt_cat']) ? $options['opt_cat'] : null; 
        */
        extract( $options );

        $data = array_merge( $this->defaults, $data );
        $type = $data['csv_post_type'] ? $data['csv_post_type'] : 'post';
        $valid_type = (function_exists('post_type_exists') && post_type_exists($type)) || in_array($type, array('post', 'page'));

        if (!$valid_type) {
            $this->log['error']["type-{$type}"] = sprintf(
                'Unknown post type "%s".', $type);
        }

        $new_post = array(
            'post_title'   => convert_chars($data['csv_post_title']),
            'post_content' => wpautop(convert_chars($data['csv_post_post'])),
            'post_status'  => $opt_draft,
            'post_type'    => $type,
            'post_date'    => $this->parse_date($data['csv_post_date']),
            'post_excerpt' => convert_chars($data['csv_post_excerpt']),
            'post_name'    => $data['csv_post_slug'],
            'post_author'  => $this->get_auth_id($data['csv_post_author']),
            'tax_input'    => $this->get_taxonomies($data),
            'post_parent'  => $data['csv_post_parent'],
        );

        // PAGES DON'T HAVE TAGS OR CATEGORIES
        if ( 'page' !== $type ) {
            $new_post['tags_input'] = $data['csv_post_tags'];

            // Setup categories before inserting - this should make insertion
            // faster, but I don't exactly remember why :) Most likely because
            // we don't assign default cat to post when csv_post_categories is not empty.
            $cats = $this->create_or_get_categories($data, $opt_cat);
            $new_post['post_category'] = $cats['post'];
        }

        // CREATE
        $id = wp_insert_post( $new_post );

        if ( $id == 0 ){
            $this->log['error'][] = "There was an error trying to create post with title" . convert_chars($data['csv_post_title']);
        } else {
            $this->log['notice'][] = "<b>Post with title " . convert_chars($data['csv_post_title']) . " created.</b>";
        }

        if ( 'page' !== $type && !$id ) {
            // REMOVE NEW CATEGORIES ON FAILURE
            foreach ( $cats['cleanup'] as $c ) {
                wp_delete_term( $c, 'category' );
            }
        }
        return $id;
    }

    /**
     * Return id of first image that matches the passed filename
     * @param string $filename csv_post_image cell contents
     * @since 0.3.9
     * 
     */
    function get_image_id( $filename ){
        // try searching titles first
        $filename = preg_replace( '/\.[^.]*$/', '', $filename );
        $filename = strtolower( str_replace(' ','-',$filename ));
        $args     = array( 'post_type' => 'attachment','name' => $filename, 'post_status' => 'publish' );
        $results  = get_posts( $args );
        // $results = get_page_by_title($filename, ARRAY_A, 'attachment');
        if( count( $results == 0 )) return;
        if( count( $results) == 1 ) return $results[0]->ID;
        elseif( count( $results ) > 1 ) {
            foreach( $results as $result ){
            if ( strpos( $result->guid, $filename ) )
                    return $result->ID;
            }
        }
    }

    /**
     * Return attachment ID based in filename
     *
     * @param string
     * @param string
     * @return string
     * @since 0.3.10
     */
    function get_ID_by_keyvalue( $csv_key, $csv_value ){
        // Used for month/year based media folders:
        // $prefix_mo_year = date('Y/m/');
        // $value = $prefix_mo_year.$csv_value;
        $value = $csv_value;
        $args = array(
            'post_type'     => 'attachment',
            'post_status'   => 'inherit',
            'meta_key'      => $csv_key,
            'meta_value'    => $value 
        );
        $the_query = new WP_Query( $args );
        if ( $the_query->have_posts() ) {
            while ( $the_query->have_posts() ) {
                $the_query->the_post();
                $imageID = $the_query->post->ID;
            }
        } else {
            // NO POSTS FOUND
        }
        wp_reset_postdata();
        return $imageID;
    }

    /**
     * Return an array of category ids for a post.
     *
     * @param string  $data csv_post_categories cell contents
     * @param integer $common_parent_id common parent id for all categories
     * @return array category ids
     */
    function create_or_get_categories($data, $common_parent_id) {
        $ids = array(
            'post' => array(),
            'cleanup' => array(),
        );
        $items = array_map('trim', explode(',', $data['csv_post_categories']));
        foreach ($items as $item) {
            if (is_numeric($item)) {
                if (get_category($item) !== null) {
                    $ids['post'][] = $item;
                } else {
                    $this->log['error'][] = "Category ID {$item} does not exist, skipping.";
                }
            } else {
                $parent_id = $common_parent_id;
                // item can be a single category name or a string such as
                // Parent > Child > Grandchild
                $categories = array_map('trim', explode('>', $item));
                if (count($categories) > 1 && is_numeric($categories[0])) {
                    $parent_id = $categories[0];
                    if (get_category($parent_id) !== null) {
                        // valid id, everything's ok
                        $categories = array_slice($categories, 1);
                    } else {
                        $this->log['error'][] = "Category ID {$parent_id} does not exist, skipping.";
                        continue;
                    }
                }
                foreach ($categories as $category) {
                    if ($category) {
                        $term = $this->term_exists($category, 'category', $parent_id);
                        if ($term) {
                            $term_id = $term['term_id'];
                        } else {
                            $term_id = wp_insert_category(array(
                                'cat_name' => $category,
                                'category_parent' => $parent_id,
                            ));
                            $ids['cleanup'][] = $term_id;
                        }
                        $parent_id = $term_id;
                    }
                }
                $ids['post'][] = $term_id;
            }
        }
        return $ids;
    }

    /**
     * Parse taxonomy data from the file
     *
     * array(
     *      // hierarchical taxonomy name => ID array
     *      'my taxonomy 1' => array(1, 2, 3, ...),
     *      // non-hierarchical taxonomy name => term names string
     *      'my taxonomy 2' => array('term1', 'term2', ...),
     * )
     *
     * @param array $data
     * @return array
     */
    function get_taxonomies($data) {
        $taxonomies = array();
        foreach ($data as $k => $v) {
            if (preg_match('/^csv_ctax_(.*)$/', $k, $matches)) {
                $t_name = $matches[1];
                if ($this->taxonomy_exists($t_name)) {
                    $taxonomies[$t_name] = $this->create_terms($t_name,
                        $data[$k]);
                } else {
                    $this->log['error'][] = "Unknown taxonomy $t_name";
                }
            }
        }
        return $taxonomies;
    }
     /**
     * Parse attachment data from the file
     * @since 0.3.9
     *
     * @param int   $post_id
     * @param array $data
     * @return array
     */
    function add_attachments( $post_id, $data ){
       // $this->log['notice'][]= 'adding attachments for id#'. $post_id;
        $attachments = array();
        foreach ($data as $k => $v) {
                if (preg_match('/^csv_attachment_(.*)$/', $k, $matches)) {
                   // $this->log['notice'][] = 'Found this attachment: ' . $matches[1] . ' with this value:' . $data[$k];
                    $a_name = $matches[1];
                   
                        $attachment[$a_name] = $data[$k];
                       
                        if(preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $data[$k])) {
                            $url = $v;
                            $id = $this->download_attachment($data[$k],$post_id,$a_name);}
                        if($a_name == 'thumbnail' && $id<>''){
                            add_post_meta($post_id, '_thumbnail_id',$id);
                        }
                }
                else if($k=='csv_post_image'){
                    $id = $this->get_image_id($v);
                    if($id<>'') add_post_meta($post_id, '_thumbnail_id',$this->get_image_id($v));
                }
            } 
            return $attachments;
    }

    /**
     * Download file from remote URL, save it to the Media Library, and return
     * the attachment id
     *
     * @param string $url
     * @param int  $post_id
     * @param string $desc
     * @return int
     */
    function download_attachment($url, $post_id, $desc){
        set_time_limit(10);
        $tmp = download_url( $url );
    	 if(strlen(trim($url))<5) return;
    	
    	// Set variables for storage
    	// fix file filename for query strings
    	//preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG|wav|mp3|pdf)/', $file, $matches);
    	 $file_array = array(
            'name' => basename( $url ),
            'tmp_name' => $tmp
                 );

    	// If error storing temporarily, unlink
    	if ( is_wp_error( $tmp ) ) {
    		@unlink($file_array['tmp_name']);
    		$file_array['tmp_name'] = '';
    	}

    	// do the validation and storage stuff
    	$id = media_handle_sideload( $file_array, $post_id, $desc );
            
    	// If error storing permanently, unlink
    	if ( is_wp_error($id) ) {
                 $this->log['error'][] = $id->get_error_message() .' : ' . $url;
    		@unlink($file_array['tmp_name']);
    		return $id;
    	}
             //$this->log['notice'][] = 'Downloaded the file. Here\'s the id: ' . $id;

    	$src = wp_get_attachment_url( $id );
             //$this->log['notice'][] = 'Saved the file successfully! Here\'s the path: ' . $src ;
        return $id;
    }
    /**
     * Return an array of term IDs for hierarchical taxonomies or the original
     * string from CSV for non-hierarchical taxonomies. The original string
     * should have the same format as csv_post_tags.
     *
     * @param string $taxonomy
     * @param string $field
     * @return mixed
     */
    function create_terms( $taxonomy, $field ) {
        if (is_taxonomy_hierarchical($taxonomy)) {
            $term_ids = array();
            foreach ($this->_parse_tax($field) as $row) {
                list($parent, $child) = $row;
                $parent_ok = true;
                if ($parent) {
                    $parent_info = $this->term_exists($parent, $taxonomy);
                    if (!$parent_info) {
                        // create parent
                        $parent_info = wp_insert_term($parent, $taxonomy);
                    }
                    if (!is_wp_error($parent_info)) {
                        $parent_id = $parent_info['term_id'];
                    } else {
                        // could not find or create parent
                        $parent_ok = false;
                    }
                } else {
                    $parent_id = 0;
                }

                if ($parent_ok) {
                    $child_info = $this->term_exists($child, $taxonomy, $parent_id);
                    if (!$child_info) {
                        // create child
                        $child_info = wp_insert_term($child, $taxonomy,
                            array('parent' => $parent_id));
                    }
                    if (!is_wp_error($child_info)) {
                        $term_ids[] = $child_info['term_id'];
                    }
                }
            }
            return $term_ids;
        } else {
            return $field;
        }
    }

    /**
     * Compatibility wrapper for WordPress term lookup.
     */
    function term_exists($term, $taxonomy = '', $parent = 0) {
        if (function_exists('term_exists')) { // 3.0 or later
            return term_exists($term, $taxonomy, $parent);
        } else {
            return is_term($term, $taxonomy, $parent);
        }
    }

    /**
     * Compatibility wrapper for WordPress taxonomy lookup.
     */
    function taxonomy_exists($taxonomy) {
        if (function_exists('taxonomy_exists')) { // 3.0 or later
            return taxonomy_exists($taxonomy);
        } else {
            return is_taxonomy($taxonomy);
        }
    }

    /**
     * Hierarchical taxonomy fields are tiny CSV files in their own right.
     *
     * @param string $field
     * @return array
     */
    function _parse_tax($field) {
        $data = array();
        if (function_exists('str_getcsv')) { // PHP 5 >= 5.3.0
            $lines = $this->split_lines($field);

            foreach ($lines as $line) {
                $data[] = str_getcsv($line, ',', '"');
            }
        } else {
            // Use temp files for older PHP versions. Reusing the tmp file for
            // the duration of the script might be faster, but not necessarily
            // significant.
            $handle = tmpfile();
            fwrite($handle, $field);
            fseek($handle, 0);

            while (($r = fgetcsv($handle, 999999, ',', '"')) !== false) {
                $data[] = $r;
            }
            fclose($handle);
        }
        return $data;
    }

    /**
     * Try to split lines of text correctly regardless of the platform the text
     * is coming from.
     */
    function split_lines($text) {
        $lines = preg_split("/(\r\n|\n|\r)/", $text);
        return $lines;
    }

    function add_comments($post_id, $data) {
        // First get a list of the comments for this post
        $comments = array();
        foreach ($data as $k => $v) {
            // comments start with cvs_comment_
            if (    preg_match('/^csv_comment_([^_]+)_(.*)/', $k, $matches) &&
                    $v != '') {
                $comments[$matches[1]] = 1;
            }
        }
        // Sort this list which specifies the order they are inserted, in case
        // that matters somewhere
        ksort($comments);

        // Now go through each comment and insert it. More fields are possible
        // in principle (see docu of wp_insert_comment), but I didn't have data
        // for them so I didn't test them, so I didn't include them.
        $count = 0;
        foreach ($comments as $cid => $v) {
            $new_comment = array(
                'comment_post_ID' => $post_id,
                'comment_approved' => 1,
            );

            if (isset($data["csv_comment_{$cid}_author"])) {
                $new_comment['comment_author'] = convert_chars(
                    $data["csv_comment_{$cid}_author"]);
            }
            if (isset($data["csv_comment_{$cid}_author_email"])) {
                $new_comment['comment_author_email'] = convert_chars(
                    $data["csv_comment_{$cid}_author_email"]);
            }
            if (isset($data["csv_comment_{$cid}_url"])) {
                $new_comment['comment_author_url'] = convert_chars(
                    $data["csv_comment_{$cid}_url"]);
            }
            if (isset($data["csv_comment_{$cid}_content"])) {
                $new_comment['comment_content'] = convert_chars(
                    $data["csv_comment_{$cid}_content"]);
            }
            if (isset($data["csv_comment_{$cid}_date"])) {
                $new_comment['comment_date'] = $this->parse_date(
                    $data["csv_comment_{$cid}_date"]);
            }

            $id = wp_insert_comment($new_comment);
            if ($id) {
                $count++;
            } else {
                $this->log['error'][] = "Could not add comment $cid";
            }
        }
        return $count;
    }

    /**
     * @param 
     * @return 
     */
     function create_custom_fields( $post_id, $data ) {
        foreach ( $data as $k => $v ) {
            // ANYTHING THAT DOESN'T START WITH csv_ IS A CUSTOM FIELD
            if ( !preg_match('/^csv_/', $k ) && $v != '') {

                if ( $k == '_csv_attached_file' ){

                    $imageID = $this->get_ID_by_keyvalue( '_wp_attached_file', $v );

                    if ( count($imageID) == 0 ){
                        $this->log['error'][] = "<b>Could not attach image <strong>{$v}</strong>. Maybe it isn't uploaded?</b>";
                    }

                    $my_post = array(
                        'ID'            => $imageID,
                        'post_parent'   => $post_id
                    );
                    wp_update_post( $my_post );
                    add_post_meta( $imageID, '_csv_attachment_image_alt', trim($data['csv_post_title']).' '.trim($data['csv_post_excerpt']));
                    add_post_meta( $post_id, '_thumbnail_id', $imageID );
                    //$this->log['notice'][] = "<b>Value is: {$v} for {$k}</b>";

                } elseif ( $k == '_csv_attached_files' ) {

                    $image_names = explode(", ", $v);
                    foreach ($image_names as $image_name){
                        $this->log['notice'][] = "<b>Trying to add {$image_name} as an attached image.</b>";
                        $imageID = $this->get_ID_by_keyvalue( '_wp_attached_file', $image_name );

                        if ( count($imageID) == 0 ){
                            $this->log['error'][] = "<b>Could not attach image <strong>{$image_name}</strong>. Maybe it isn't uploaded?</b>";
                        }

                        $my_post = array(
                        'ID'            => $imageID,
                        'post_parent'   => $post_id
                        );
                        wp_update_post( $my_post );
                    }

                } else {

                    /***** Added: 0.3.9 *****/
                    // if value is serialized unserialize it
                    if ( is_serialized($v) ) {
                        $v = unserialize($v);
                        // unserialized array will be re-serialized with add_post_meta()
                    } elseif ( strpos($v,'::') ) {
                        // import data and serialize it formatted as: key::value[]key::value
                        $array = explode("[]",$v);
                        foreach ($array as $lineNum => $line)
                        {
                            list( $key, $value ) = explode( "::", $line );
                            $newArray[$key] = $value;
                        }
                        $v = $newArray;
                    }
                    /***** EoF *****/

                    add_post_meta( $post_id, $k, $v );

                }

            }
        }
        
    }

    function get_auth_id($author) {
        if (is_numeric($author)) {
            return $author;
        }
        /* Removed: 0.3.9
        // get_userdatabylogin is deprecated as of 3.3.0       
        if (function_exists('get_user_by')) {       
             $author_data = get_user_by('login', $author);       
        } else {        
             $author_data = get_userdatabylogin($author);        
        } 
        */

        // @since 0.3.9
        $author_data = get_user_by('login', $author);
        return ( $author_data ) ? $author_data->ID : 0;
    }

    /**
     * Convert date in CSV file to 1999-12-31 23:52:00 format
     *
     * @param string $data
     * @return string
     */
    function parse_date($data) {
        $timestamp = strtotime($data);
        if (false === $timestamp) {
            return '';
        } else {
            return date('Y-m-d H:i:s', $timestamp);
        }
    }

    /**
     * Delete BOM from UTF-8 file.
     *
     * @param string $fname
     * @return void
     */
    function stripBOM($fname) {
        $res = fopen($fname, 'rb');
        if (false !== $res) {
            $bytes = fread($res, 3);
            if ($bytes == pack('CCC', 0xef, 0xbb, 0xbf)) {
                $this->log['notice'][] = 'Getting rid of byte order mark...';
                fclose($res);

                $contents = file_get_contents($fname);
                if (false === $contents) {
                    trigger_error('Failed to get file contents.', E_USER_WARNING);
                }
                $contents = substr($contents, 3);
                $success = file_put_contents($fname, $contents);
                if (false === $success) {
                    trigger_error('Failed to put file contents.', E_USER_WARNING);
                }
            } else {
                fclose($res);
            }
        } else {
            $this->log['error'][] = 'Failed to open file, aborting.';
        }
    }
}

$csvImporterPlugin = new CSVImporterPlugin;