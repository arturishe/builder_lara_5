<?php

if (! function_exists('setting')) {
    function setting($value)
    {
        return Vis\Builder\Setting::get($value);
    }
}

if (! function_exists('dr')) {
    function dr($array)
    {
        echo '<pre>';
        die(print_r($array));
    }
}

if (! function_exists('print_arr')) {
    function print_arr($array)
    {
        echo '<pre>';
        print_r($array);
        echo '</pre>';
    }
}

if (! function_exists('cartesian')) {
    function cartesian($arr, $isElementsDuplicated = false)
    {
        $variant = [];
        $result = [];
        $arrayCount = count($arr);

        return cartesianRecurseIt($arr, $variant, -1, $result, $arrayCount, $isElementsDuplicated);
    }
}

if (! function_exists('cartesianRecurseIt')) {
    function cartesianRecurseIt($arr, $variant, $level, $result, $arrayCount, $isElementsDuplicated)
    {
        $level++;
        if ($level < $arrayCount) {
            foreach ($arr[$level] as $val) {
                $variant[$level] = $val;
                $result = cartesianRecurseIt($arr, $variant, $level, $result, $arrayCount, $isElementsDuplicated);
            }
        } else {
            if (! $isElementsDuplicated) {
                $result[] = $variant;
            } else {
                if (count(array_flip(array_flip($variant))) == $arrayCount) {
                    $result[] = $variant;
                }
            }
        }

        return $result;
    }
}

if (! function_exists('remove_bom')) {
    function remove_bom($val)
    {
        if (substr($val, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
            $val = substr($val, 3);
        }

        return $val;
    }
}

if (! function_exists('glide')) {
    function glide($source, $options = [])
    {
        $img = new Vis\Builder\Img();

        return $img->get($source, $options);
    }
}

if (! function_exists('filesize_format')) {
    function filesize_format($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 1, '.', '').' Gb';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 1, '.', '').' Mb';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 1, '.', '').' Kb';
        } elseif ($bytes > 1) {
            $bytes = $bytes.' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes.' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
}

//return localization url
if (! function_exists('geturl')) {
    function geturl($url, $locale = false, $attributes = [])
    {
        if (! $locale) {
            $locale = App::getLocale();
        }

        return LaravelLocalization::getLocalizedURL($locale, $url, $attributes);
    }
}

/*
 * translate phrase cms
 * @param string $phrase
 *
 * @return string
 */
if (! function_exists('__cms')) {
    function __cms($phrase)
    {
        $this_lang = Cookie::get('lang_admin');

        $array_translate = Vis\TranslationsCMS\Trans::fillCacheTrans();

        if (isset($array_translate[$phrase][$this_lang])) {
            return $array_translate[$phrase][$this_lang];
        } else {
            return $phrase;
        }
    }
}

/*
 * get realy ip user
 *
 * @return string
 */
if (! function_exists('getIp')) {
    function getIp()
    {
        if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }
}

/*
 *  recurse create url for tree
 *  @return string
 */
if (! function_exists('recurseMyTree')) {
    function recurseMyTree($tree, $node, &$slugs = [])
    {
        if (! $node['parent_id']) {
            return $node['slug'];
        }

        $slugs[] = $node['slug'];
        $idParent = $node['parent_id'];
        if ($idParent) {
            $parent = $tree[$idParent];
            recurseMyTree($tree, $parent, $slugs);
        }

        return implode('/', array_reverse($slugs));
    }
}

/*
 * Returns entire string with current locale postfix, ex. string_ua
 *
 * @param  string
 * @return string
 */
if (! function_exists('getWithLocalePostfix')) {
    function getWithLocalePostfix($string)
    {
        $currentLocale = LaravelLocalization::getCurrentLocale();

        return $currentLocale == LaravelLocalization::getDefaultLocale() ? $string : $string.'_'.$currentLocale;
    }
}
