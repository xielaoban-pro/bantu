<?php
/*
Plugin Name: Bantu Image Fixer
Plugin URL: https://www.sdczz.com
Description: 禁止 WordPress 生成缩略图/剪切图片，提供工具将现有文章的特色图片替换回原图（通过清除缩略图元数据）。
Version: 1.3
Author: 声达网
Author URI: https://www.sdczz.com
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 功能 1: 禁止剪切/生成多尺寸图片
 * ----------------------------------------------------------------------------
 */

// 禁止生成中间尺寸图片 (thumbnail, medium, large 等)
add_filter( 'intermediate_image_sizes_advanced', 'bantu_disable_image_sizes' );
function bantu_disable_image_sizes( $sizes ) {
    return array(); // 返回空数组，禁止生成任何尺寸
}

// 禁止生成超大尺寸缩放 (WordPress 5.3+ 默认会生成 -scaled 图片)
add_filter( 'big_image_size_threshold', '__return_false' );

/**
 * 功能 2: 扫描并修复现有文章特色图片
 * ----------------------------------------------------------------------------
 */

// 添加管理菜单
add_action( 'admin_menu', 'bantu_add_admin_menu' );
function bantu_add_admin_menu() {
    add_options_page(
        'Bantu 图片修复工具', 
        'Bantu 图片修复', 
        'manage_options', 
        'bantu-image-fixer', 
        'bantu_render_admin_page'
    );
}

// 渲染管理页面
function bantu_render_admin_page() {
    // 处理表单提交
    if ( isset( $_POST['bantu_fix_images'] ) && check_admin_referer( 'bantu_fix_images_nonce' ) ) {
        bantu_process_images();
    }
    ?>
    <div class="wrap">
        <h1>Bantu 图片修复工具</h1>
        <p>本插件已自动<strong>禁止</strong>新上传图片生成缩略图。</p>
        <hr>
        <h2>修复现有文章特色图片</h2>
        <p>点击下方按钮将扫描所有已发布的文章，并强制将其“特色图片”重置为<strong>原图</strong>。</p>
        <p class="description">
            <strong>原理说明：</strong> 程序会查找文章的特色图片，并从数据库中删除其“缩略图尺寸”记录。<br>
            这会迫使 WordPress 在前端调用该图片时，因为找不到缩略图记录而直接使用<strong>原图</strong>。<br>
            （例如：从 <code>image-768x432.webp</code> 变为 <code>image.webp</code>）
        </p>
        <p style="color: red;">注意：此操作不可逆（除非重新使用“重新生成缩略图”类插件）。建议操作前备份数据库。</p>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'bantu_fix_images_nonce' ); ?>
            <input type="submit" name="bantu_fix_images" class="button button-primary" value="开始扫描并替换为原图" onclick="return confirm('确定要执行吗？这将修改图片元数据。');">
        </form>
    </div>
    <?php
}

// 处理逻辑
function bantu_process_images() {
    // 获取所有文章 (可以根据需要修改 post_type)
    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => '_thumbnail_id',
                'compare' => 'EXISTS'
            )
        )
    );

    $posts = get_posts( $args );
    $count = 0;

    if ( $posts ) {
        foreach ( $posts as $post_id ) {
            // 获取特色图片 ID
            $thumbnail_id = get_post_thumbnail_id( $post_id );
            
            if ( $thumbnail_id ) {
                // 获取图片元数据
                $meta = wp_get_attachment_metadata( $thumbnail_id );
                
                // 如果存在 'sizes' 数组，说明有剪切版本，我们需要删除它
                if ( ! empty( $meta['sizes'] ) ) {
                    unset( $meta['sizes'] ); // 删除尺寸信息
                    
                    // 更新元数据
                    wp_update_attachment_metadata( $thumbnail_id, $meta );
                    $count++;
                }
            }
        }
        echo '<div class="updated notice is-dismissible"><p>操作完成！已修复 <strong>' . $count . '</strong> 篇文章的特色图片。</p></div>';
    } else {
        echo '<div class="notice notice-warning"><p>未找到带有特色图片的内容。</p></div>';
    }
}
