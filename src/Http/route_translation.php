<?php

Route::group(['middleware' => ['web']], function () {
    Route::group(
        ['prefix' => 'admin', 'middleware' => 'auth.admin'],
        function () {
            Route::any('translations_cms/phrases', [
                'as' => 'phrases_all',
                'uses' => 'Vis\TranslationsCMS\TranslateController@fetchIndex', ]);

            if (Request::ajax()) {
                Route::post('translations_cms/create_pop', [
                    'as' => 'create_pop',
                    'uses' => 'Vis\TranslationsCMS\TranslateController@fetchCreate', ]);
                Route::post('translations_cms/translate', [
                    'as' => 'translate',
                    'uses' => 'Vis\TranslationsCMS\TranslateController@doTranslate', ]);
                Route::post('translations_cms/add_record', [
                    'as' => 'add_record',
                    'uses' => 'Vis\TranslationsCMS\TranslateController@doSaveTranslate', ]);
                Route::post('translations_cms/change_text_lang', [
                    'as' => 'change_text_lang',
                    'uses' => 'Vis\TranslationsCMS\TranslateController@doSavePhrase', ]);
                Route::post('translations_cms/del_record', [
                    'as' => 'del_record',
                    'uses' => 'Vis\TranslationsCMS\TranslateController@doDelelePhrase', ]);
            }
        }
    );
});
