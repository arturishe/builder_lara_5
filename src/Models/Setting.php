<?php

namespace Vis\Builder;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Setting extends Eloquent
{
    use \Venturecraft\Revisionable\RevisionableTrait;

    protected $fillable
        = [
            'type',
            'title',
            'slug',
            'value',
            'group_type',
        ];
    protected $table = 'settings';

    public static $rules
        = [
            'title' => 'required',
            'slug' => 'required|max:256|unique:settings,slug,',
        ];

    public $timestamps = false;

    /*
     * return value setting
     */
    public static function get($slug, $default = '')
    {
        if (Cache::tags('settings')->has($slug)) {
            return Cache::tags('settings')->get($slug);
        } else {
            $setting = self::where('slug', 'like', $slug)->first();

            if (isset($setting->id)) {
                $value = $setting->value ?: $default;

                if ($setting->type == 2 || $setting->type == 3 || $setting->type == 5) {
                    $value = $setting->selectValues();
                }

                Cache::tags('settings')->forever($slug, $value);

                return $value;
            }
        }
    }

    // end get

    public static function getWithLang($slug, $default = '')
    {
        $prefixLang = self::getPrefixLang();
        $key = $slug.$prefixLang;

        if (Cache::tags('settings')->has($key)) {
            return Cache::tags('settings')->get($key);
        } else {
            $setting = self::where('slug', 'like', $slug)->first();

            if (isset($setting->id)) {
                $field = 'value'.$prefixLang;
                $value = $setting->$field ?: $default;

                Cache::tags('settings')->forever($key, $value);

                return $value;
            }
        }
    }

    public static function getPrefixLang()
    {
        $lang = App::getLocale();
        $defaultLocale = config('translations.config.def_locale');

        if ($lang != $defaultLocale) {
            return '_'.$lang;
        }
    }

    public static function getItem($ids)
    {
        if (! $ids) {
            return [];
        }

        return SettingSelect::find($ids);
    }

    //end getItem

    public static function doSaveSetting($data, $file)
    {
        if ($data['id'] == 0) {
            $settings = new self;
        } else {
            $settings = self::find($data['id']);
        }

        $settings->title = $data['title'];
        $settings->slug = $data['slug'];
        $settings->type = $data['type'];
        $settings->group_type = $data['group'];

        if ($data['type'] < 2 || $data['type'] == 6) {
            $settings->value = $data['value'.$data['type']];
        }

        //yes/no
        if ($data['type'] == 7) {
            $settings->value = $data['status'];
        }

        //if type file
        if ($data['type'] == 4 && $file) {
            $destinationPath = 'storage/settings';
            $ext = $file->getClientOriginalExtension();
            $hashname = md5(time()).'.'.$ext;
            $full_path_img = '/'.$destinationPath.'/'.$hashname;
            $file->move($destinationPath, $hashname);
            $settings->value = $full_path_img;
        }

        if (count(config('builder.settings.langs')) && ($data['type'] < 2 || $data['type'] == 6)) {
            foreach (config('builder.settings.langs') as $prefix => $value) {
                $field = 'value'.$prefix;

                if (isset($data['value'.$data['type'].$prefix])) {
                    $settings->$field = $data['value'.$data['type'].$prefix];
                }
            }
        }

        $settings->save();

        //если тип список
        if ($data['type'] == 2) {
            $i = 0;
            foreach ($data['select'] as $k => $el) {
                $i++;
                if ($el) {
                    if (is_numeric($k)) {
                        $el = trim($el);
                        if ($el) {
                            $SettingSelect = SettingSelect::find($k);
                            $SettingSelect->id_setting = $settings->id;
                            $SettingSelect->value = $el;
                            $SettingSelect->priority = $i;
                            $SettingSelect->save();
                        }
                    } else {
                        foreach ($data['select']['new'] as $el_new) {
                            $el_new = trim($el_new);
                            if ($el_new) {
                                $SettingSelect = new SettingSelect;
                                $SettingSelect->id_setting = $settings->id;
                                $SettingSelect->value = $el_new;
                                $SettingSelect->priority = $i;
                                $SettingSelect->save();
                                $i++;
                            }
                        }
                    }
                }
            }
        }

        //if type double list
        if ($data['type'] == 3) {
            $i = 0;
            foreach ($data['select21'] as $k => $el) {
                $i++;
                if ($el) {
                    if (is_numeric($k)) {
                        $el = trim($el);
                        if ($el) {
                            $SettingSelect = SettingSelect::find($k);
                            $SettingSelect->id_setting = $settings->id;
                            $SettingSelect->value = $el;
                            $SettingSelect->value2 = $data['select22'][$k];
                            $SettingSelect->priority = $i;
                            $SettingSelect->save();
                        }
                    } else {
                        foreach ($data['select21']['new'] as $k_new => $el_new) {
                            $el_new = trim($el_new);
                            if ($el_new) {
                                $SettingSelect = new SettingSelect;
                                $SettingSelect->id_setting = $settings->id;
                                $SettingSelect->value = $el_new;
                                $SettingSelect->value2
                                    = $data['select22']['new'][$k_new];
                                $SettingSelect->priority = $i;
                                $SettingSelect->save();
                                $i++;
                            }
                        }
                    }
                }
            }
        }

        //if the triple list
        if ($data['type'] == 5) {
            $i = 0;
            foreach ($data['select31'] as $k => $el) {
                $i++;
                if ($el) {
                    if (is_numeric($k)) {
                        $el = trim($el);
                        if ($el) {
                            $SettingSelect = SettingSelect::find($k);
                            $SettingSelect->id_setting = $settings->id;
                            $SettingSelect->value = $el;
                            $SettingSelect->value2 = $data['select32'][$k];
                            $SettingSelect->value3 = $data['select33'][$k];
                            $SettingSelect->priority = $i;
                            $SettingSelect->save();
                        }
                    } else {
                        foreach ($data['select31']['new'] as $k_new => $el_new) {
                            $el_new = trim($el_new);
                            if ($el_new) {
                                $SettingSelect = new SettingSelect;
                                $SettingSelect->id_setting = $settings->id;
                                $SettingSelect->value = $el_new;
                                $SettingSelect->value2
                                    = $data['select32']['new'][$k_new];
                                $SettingSelect->value3
                                    = $data['select33']['new'][$k_new];
                                $SettingSelect->priority = $i;
                                $SettingSelect->save();
                                $i++;
                            }
                        }
                    }
                }
            }
        }

        self::reCacheSettings();

        return $settings;
    }

    /*
    * recache settings
    */
    public static function reCacheSettings()
    {
        Cache::tags('settings')->flush();
    }

    // end reCacheSettings

    /*
     * delete setting
     */
    public static function doDelete($id)
    {
        if (is_numeric($id)) {
            $id_page = Input::get('id');
            $page = self::find($id_page);

            Event::fire('setting.delete', [$page]);

            $page->delete();

            self::reCacheSettings();
        }
    }

    // end doDelete

    /*
     * validation
     */
    public static function isValid($data, $id)
    {
        self::$rules['slug'] .= $id;

        $validator = Validator::make($data, self::$rules);
        if ($validator->fails()) {
            return Response::json(
                [
                    'status' => 'error',
                    'errors_messages' => $validator->messages(),
                ]
            );
        } else {
            return false;
        }
    }

    //end isValid

    /*
     * join settingSelect
     */
    public function selectValues()
    {
        return $this->hasMany('Vis\Builder\SettingSelect', 'id_setting')
            ->orderBy('priority')->get()->toArray();
    }

    // end select_get
}
