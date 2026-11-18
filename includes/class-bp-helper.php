<?php

declare(strict_types=1);
/********************************************************************
 * Copyright (C) 2019 Darko Gjorgjijoski (https://darkog.com)
 *
 * This file is part of WP Batch Processing
 *
 * WP Batch Processing is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * WP Batch Processing is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WP Batch Processing. If not, see <https://www.gnu.org/licenses/>.
 **********************************************************************/

// The direct-access guard runs as the file loads, before any test, and dies when it fires, so no test can reach it
// in this process; tests/Unit/LoadTimeTest.php pins it in a subprocess.
// @pest-mutate-ignore: RemoveNot
if (! defined('ABSPATH')) { // @pest-mutate-ignore: IfNegated
    exit('Direct access is not allowed.');
}

/**
 * Class WP_BP_Helper
 */
class WP_BP_Helper
{
    /**
     * Renders a view
     *
     * @param  string  $view
     * @param  array<string, mixed>  $data
     */
    public static function render($view, $data = []): void
    {
        $path = WP_BP_PATH.'views'.DIRECTORY_SEPARATOR.$view.'.php';
        if (file_exists($path)) {
            if (! empty($data)) {
                extract($data);
            }

            include $path;
        } else {
            echo 'View '.$view.' not found';
        }
    }

    /**
     * Reads an Advanced Custom Fields list value as a list.
     *
     * An empty value is an empty list. A value that is not an array draws a warning, as looping over it
     * would, and is an empty list too.
     *
     * @param  mixed  $value  What get_field() returned.
     * @return array<int, mixed>
     */
    public static function acf_list($value): array
    {
        if (! $value) {
            return [];
        }

        if (! is_array($value)) {
            trigger_error(sprintf('foreach() argument must be of type array|object, %s given', get_debug_type($value)), E_USER_WARNING);

            return [];
        }

        return array_values($value);
    }

    /**
     * Reads an Advanced Custom Fields list of ids as integers; numeric strings count, and an entry that is
     * not an id draws a warning and is left out.
     *
     * @param  mixed  $value  What get_field() returned.
     * @return array<int, int>
     */
    public static function acf_ids($value): array
    {
        $ids = [];
        foreach (self::acf_list($value) as $entry) {
            if (is_int($entry)) {
                $ids[] = $entry;
            } elseif (is_string($entry) && is_numeric($entry)) {
                $ids[] = (int) $entry;
            } else {
                trigger_error(sprintf('Skipped a list entry that is not an id: %s given', get_debug_type($entry)), E_USER_WARNING);
            }
        }

        return $ids;
    }

    /**
     * Reads an Advanced Custom Fields list of posts, which hold ids or post objects depending on the field's
     * return format; an id is an integer, and an entry that is neither draws a warning and is left out.
     *
     * @param  mixed  $value  What get_field() returned.
     * @return array<int, int|WP_Post>
     */
    public static function acf_posts($value): array
    {
        $posts = [];
        foreach (self::acf_list($value) as $entry) {
            if (is_int($entry) || $entry instanceof WP_Post) {
                $posts[] = $entry;
            } elseif (is_string($entry) && is_numeric($entry)) {
                $posts[] = (int) $entry;
            } else {
                trigger_error(sprintf('Skipped a list entry that is not a post: %s given', get_debug_type($entry)), E_USER_WARNING);
            }
        }

        return $posts;
    }

    /**
     * Reads an Advanced Custom Fields value as a string; anything that is not a scalar is an empty string.
     *
     * @param  mixed  $value  What get_field() returned.
     */
    public static function acf_string($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Reads term meta, as get_term_meta() returns it with no key, as the first value of each meta key, with a
     * serialized value unserialized.
     *
     * @param  mixed  $meta  What get_term_meta() returned.
     * @return array<string, mixed>
     */
    public static function first_meta_values($meta): array
    {
        $values = [];
        if (! is_array($meta)) {
            return $values;
        }

        foreach ($meta as $key => $entries) {
            $value = is_array($entries) ? ($entries[0] ?? null) : null;
            if (is_string($value) && is_serialized($value)) {
                $value = unserialize($value);
            }

            // The cast is for the analyzer: PHP turns a numeric string key back into an integer key, so the
            // result is the same with or without it and no test can tell.
            // @pest-mutate-ignore: RemoveStringCast
            $values[(string) $key] = $value;
        }

        return $values;
    }

    /**
     * Tells whether a term already has its post of the target post type, so a batch that makes a post per
     * term can run again without making a second one.
     *
     * The term's post is a post of the type titled with the term's name: the one an earlier run made, at the
     * offset id or, when that id was taken, at an ordinary one, or one made by hand. A post of any other type,
     * a page included, does not count, and neither does a trashed or auto-draft post, as `post_status => any`
     * leaves them out.
     *
     * The offset id is deliberately not consulted. When an import id is taken, WordPress gives the post an
     * ordinary auto-increment id, which can equal a later term's offset id, so a post of the type at a term's
     * offset id may belong to another term. A generated post retitled since it was made is not found, and a
     * run makes the term a second one.
     *
     * @param  string  $title  The term's name.
     * @param  string  $post_type  The post type the batch makes.
     */
    public static function term_post_exists(string $title, string $post_type): bool
    {
        $found = get_posts([
            'post_type' => $post_type,
            'title' => $title,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);

        return $found !== [];
    }
}
