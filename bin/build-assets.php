<?php
/**
 * Build WordPress.org directory assets from the official ZipQuantum logo.
 */

$root      = dirname( __DIR__ );
$output    = $root . DIRECTORY_SEPARATOR . 'wordpress-org-assets';
$logo_path = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'zipquantum-logo.png';
$font      = 'C:\\Windows\\Fonts\\segoeui.ttf';
$font_bold = 'C:\\Windows\\Fonts\\segoeuib.ttf';
$deep_teal = array( 10, 41, 40 );
$teal      = array( 25, 108, 105 );
$lime      = array( 140, 255, 0 );

if ( ! extension_loaded( 'gd' ) || ! is_file( $logo_path ) || ! is_file( $font ) || ! is_file( $font_bold ) ) {
	fwrite( STDERR, "GD, the official logo, and Segoe UI fonts are required.\n" );
	exit( 1 );
}
if ( ! is_dir( $output ) && ! mkdir( $output, 0777, true ) && ! is_dir( $output ) ) {
	fwrite( STDERR, "Could not create the WordPress.org assets directory.\n" );
	exit( 1 );
}

$logo = imagecreatefrompng( $logo_path );

$banner = imagecreatetruecolor( 1544, 500 );
imagefill( $banner, 0, 0, imagecolorallocate( $banner, ...$deep_teal ) );
$banner_teal = imagecolorallocate( $banner, ...$teal );
$banner_lime = imagecolorallocate( $banner, ...$lime );
$white       = imagecolorallocate( $banner, 255, 255, 255 );

imagefilledpolygon( $banner, array( 0, 0, 440, 0, 230, 500, 0, 500 ), $banner_teal );
imagefilledpolygon( $banner, array( 1544, 0, 1324, 0, 1114, 500, 1544, 500 ), $banner_teal );
imagefilledrectangle( $banner, 0, 474, 1544, 499, $banner_lime );
imagefilledrectangle( $banner, 465, 82, 1079, 260, $white );
imagecopyresampled( $banner, $logo, 505, 108, 0, 0, 534, 116, imagesx( $logo ), imagesy( $logo ) );

$title = 'Smart Links & QR Codes';
$box   = imagettfbbox( 48, 0, $font_bold, $title );
$x     = (int) ( ( 1544 - ( $box[2] - $box[0] ) ) / 2 );
imagettftext( $banner, 48, 0, $x, 352, $white, $font_bold, $title );
$tagline = 'Link anything. Convert everything.';
$box     = imagettfbbox( 25, 0, $font, $tagline );
$x       = (int) ( ( 1544 - ( $box[2] - $box[0] ) ) / 2 );
imagettftext( $banner, 25, 0, $x, 410, $banner_lime, $font, $tagline );
imagepng( $banner, $output . DIRECTORY_SEPARATOR . 'banner-1544x500.png', 9 );

$banner_small = imagescale( $banner, 772, 250, IMG_BICUBIC_FIXED );
imagepng( $banner_small, $output . DIRECTORY_SEPARATOR . 'banner-772x250.png', 9 );

$icon = imagecreatetruecolor( 256, 256 );
imagefill( $icon, 0, 0, imagecolorallocate( $icon, ...$deep_teal ) );
$icon_teal = imagecolorallocate( $icon, ...$teal );
$icon_lime = imagecolorallocate( $icon, ...$lime );
imagefilledpolygon( $icon, array( 0, 0, 112, 0, 40, 256, 0, 256 ), $icon_teal );
imagefilledrectangle( $icon, 0, 240, 256, 255, $icon_lime );
imagecopyresampled( $icon, $logo, 30, 42, 0, 0, 196, 164, 78, imagesy( $logo ) );
imagepng( $icon, $output . DIRECTORY_SEPARATOR . 'icon-256x256.png', 9 );

$icon_small = imagescale( $icon, 128, 128, IMG_BICUBIC_FIXED );
imagepng( $icon_small, $output . DIRECTORY_SEPARATOR . 'icon-128x128.png', 9 );

imagedestroy( $icon_small );
imagedestroy( $icon );
imagedestroy( $banner_small );
imagedestroy( $banner );
imagedestroy( $logo );

fwrite( STDOUT, "WordPress.org assets generated in {$output}.\n" );
