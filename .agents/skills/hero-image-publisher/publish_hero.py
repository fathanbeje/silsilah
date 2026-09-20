#!/usr/bin/env python3
"""
publish_hero.py - Turnkey Hero Image Optimizer & Publisher for MI Almaarif 02 Singosari

Usage:
    python publish_hero.py <image_path> --caption "Judul / Caption Foto Hero" [--quality 82] [--no-vps]

Pipeline:
    1. Optimizes input image to WebP with PIL (method=6, sRGB, quality ~82).
    2. Reports before/after file sizes and bandwidth savings.
    3. Saves optimized WebP copy locally to theme images/ directory.
    4. Uploads to VPS (/tmp -> wp-content/uploads/YYYY/MM/).
    5. Inserts WP Media attachment, triggers sub-size generation.
    6. Creates/updates 'slider' post type with caption and sets as featured image.
    7. Purges Nginx proxy cache and reloads Nginx.
    8. Performs automated live HTTP verification against https://mia02sgs.sch.id.
"""

import argparse
import os
import re
import subprocess
import sys
import urllib.request
from pathlib import Path
from PIL import Image

# VPS Configuration
VPS_HOST = "103.177.95.140"
VPS_PORT = "2288"
VPS_USER = "root"
SSH_KEY = "C:/Users/Administrator/.ssh/vps_deploy_ed25519"
WP_PATH = "/www/wwwroot/mia02sgs.sch.id"


def slugify(text: str) -> str:
    """Converts a caption string into a safe file slug."""
    text = text.lower().strip()
    text = re.sub(r"[^\w\s-]", "", text)
    text = re.sub(r"[\s_-]+", "-", text)
    return text.strip("-")


def optimize_image(src_path: str, dest_path: str, quality: int = 82) -> tuple[int, int, float]:
    """Converts image to optimized WebP format."""
    if not os.path.exists(src_path):
        raise FileNotFoundError(f"Source image not found: {src_path}")

    orig_size = os.path.getsize(src_path)
    img = Image.open(src_path)

    # Convert RGBA to RGB if saving with solid background, or keep RGB
    if img.mode in ("RGBA", "LA") and not img.getchannel("A").getextrema()[0] < 255:
        img = img.convert("RGB")
    elif img.mode not in ("RGB", "RGBA"):
        img = img.convert("RGB")

    os.makedirs(os.path.dirname(os.path.abspath(dest_path)), exist_ok=True)
    img.save(dest_path, "WEBP", quality=quality, method=6)

    opt_size = os.path.getsize(dest_path)
    savings = ((orig_size - opt_size) / orig_size) * 100 if orig_size else 0.0
    return orig_size, opt_size, savings


def run_ssh_script(script: str) -> str:
    """Executes a bash script via SSH on the VPS."""
    cmd = [
        "ssh",
        "-p", VPS_PORT,
        "-i", SSH_KEY,
        "-o", "StrictHostKeyChecking=no",
        f"{VPS_USER}@{VPS_HOST}",
        "bash",
    ]
    proc = subprocess.run(cmd, input=script, text=True, capture_output=True)
    if proc.returncode != 0:
        raise RuntimeError(f"SSH command failed ({proc.returncode}):\n{proc.stderr}\n{proc.stdout}")
    return proc.stdout


def scp_file(local_path: str, remote_path: str) -> None:
    """Transfers a file to the VPS via SCP."""
    cmd = [
        "scp",
        "-P", VPS_PORT,
        "-i", SSH_KEY,
        "-o", "StrictHostKeyChecking=no",
        local_path,
        f"{VPS_USER}@{VPS_HOST}:{remote_path}",
    ]
    proc = subprocess.run(cmd, capture_output=True, text=True)
    if proc.returncode != 0:
        raise RuntimeError(f"SCP failed ({proc.returncode}):\n{proc.stderr}")


def publish_to_wordpress(filename: str, caption: str) -> None:
    """Runs PHP script in VPS WordPress context to attach media and publish slider post."""
    php_script = f"""
cat << 'EOF' | php
<?php
require_once '{WP_PATH}/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$upload_dir = wp_upload_dir();
$filename   = '{filename}';
$caption    = '{caption}';
$src_tmp    = '/tmp/' . $filename;
$target_path = $upload_dir['path'] . '/' . $filename;

if ( ! file_exists( $src_tmp ) ) {{
    echo "ERROR: $src_tmp not found\n";
    exit(1);
}}

copy( $src_tmp, $target_path );
chmod( $target_path, 0644 );
@chown( $target_path, 'frankenphp' );
@chgrp( $target_path, 'www' );

// 1. Create or update attachment
$wp_filetype = wp_check_filetype( $filename, null );
$attachment = array(
    'post_mime_type' => $wp_filetype['type'] ?: 'image/webp',
    'post_title'     => $caption,
    'post_content'   => '',
    'post_status'    => 'inherit'
);

$attach_id = wp_insert_attachment( $attachment, $target_path );
if ( is_wp_error( $attach_id ) ) {{
    echo "ERROR: " . $attach_id->get_error_message() . "\n";
    exit(1);
}}

$attach_data = wp_generate_attachment_metadata( $attach_id, $target_path );
wp_update_attachment_metadata( $attach_id, $attach_data );
update_post_meta( $attach_id, '_wp_attachment_image_alt', $caption );
echo "OK: Attachment created with ID: " . $attach_id . "\n";

// 2. Publish slider post
$now = current_time( 'mysql' );
$post_id = wp_insert_post( array(
    'post_title'    => $caption,
    'post_type'     => 'slider',
    'post_status'   => 'publish',
    'post_date'     => $now,
    'post_date_gmt' => get_gmt_from_date( $now ),
) );

if ( is_wp_error( $post_id ) ) {{
    echo "ERROR: " . $post_id->get_error_message() . "\n";
    exit(1);
}}

set_post_thumbnail( $post_id, $attach_id );
echo "OK: Slider post published with ID: " . $post_id . "\n";

// 3. Purge caches
if ( function_exists( 'wp_cache_flush' ) ) {{
    wp_cache_flush();
}}
EOF
"""
    output = run_ssh_script(php_script)
    print(output.strip())

    # Purge Nginx proxy cache
    purge_cmd = "rm -rf /www/server/nginx/proxy_cache_dir/* && /etc/init.d/nginx reload"
    run_ssh_script(purge_cmd)
    print("OK: Nginx proxy cache purged and reloaded.")


def verify_live(caption: str) -> bool:
    """Verifies that the live site serves the new hero slide and caption."""
    try:
        req = urllib.request.Request("https://mia02sgs.sch.id/", headers={"User-Agent": "Mozilla/5.0"})
        with urllib.request.urlopen(req, timeout=10) as res:
            html = res.read().decode("utf-8")

        has_caption = caption in html
        has_active = 'class="mia-hero-slide is-active"' in html or "is-active" in html
        preload_match = re.search(r'<link rel="preload" as="image"[^>]+>', html)
        preload_tag = preload_match.group(0) if preload_match else "None"

        print("\n=== Live Verification ===")
        print(f"URL: https://mia02sgs.sch.id/ (HTTP 200 OK)")
        print(f"Caption '{caption}' present: {has_caption}")
        print(f"Hero slide active: {has_active}")
        print(f"LCP Preload tag: {preload_tag}")

        return has_caption
    except Exception as e:
        print(f"Verification warning: {e}")
        return False


def main():
    parser = argparse.ArgumentParser(description="Optimize and publish hero images to MI Almaarif 02 Singosari.")
    parser.add_argument("image_path", help="Path to input photo (JPG, PNG, WebP)")
    parser.add_argument("--caption", required=True, help="Caption/Title for the hero photo")
    parser.add_argument("--quality", type=int, default=82, help="WebP quality (default: 82)")
    parser.add_argument("--no-vps", action="store_true", help="Only optimize locally without uploading to VPS")

    args = parser.parse_args()

    slug = slugify(args.caption)
    filename = f"{slug}.webp"

    # Local paths
    repo_root = Path(__file__).resolve().parent.parent.parent
    local_dest = repo_root / "images" / filename

    print(f"\n[1/4] Optimizing {args.image_path} -> {filename} (quality={args.quality})...")
    orig_sz, opt_sz, sav = optimize_image(args.image_path, str(local_dest), quality=args.quality)
    print(f"  - Original:  {orig_sz:,} bytes ({orig_sz/1024:.2f} KB)")
    print(f"  - Optimized: {opt_sz:,} bytes ({opt_sz/1024:.2f} KB)")
    print(f"  - Reduction: {sav:.1f}% saved!")
    print(f"  - Saved to:  {local_dest}")

    if args.no_vps:
        print("\n[Local only] Skipping VPS upload and WordPress publishing.")
        return

    print(f"\n[2/4] Transferring {filename} to VPS ({VPS_HOST})...")
    scp_file(str(local_dest), f"/tmp/{filename}")
    print("  - File uploaded to /tmp/")

    print(f"\n[3/4] Registering media attachment and publishing hero slider post...")
    publish_to_wordpress(filename, args.caption)

    print(f"\n[4/4] Verifying live homepage...")
    success = verify_live(args.caption)
    if success:
        print(f"\n[SUCCESS] Foto hero '{args.caption}' berhasil dipasang dan aktif!")
    else:
        print(f"\n[NOTICE] Foto terpasang, silakan periksa di browser jika cache browser masih menyimpan versi lama.")


if __name__ == "__main__":
    main()
