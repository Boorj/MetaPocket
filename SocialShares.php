<?php
namespace Bolt\Extension\Mapple\MetaPocketExtension;


class SocialShares
{
    const DEFAULT_LANGUAGE = 'ru';


    static function get_socials_data($social, $null_value = []) {
        $socials = [
            'facebook'      => [
                'lang' => ['ru'=>['Фэйсбук', 'на Фэйсбуке']],
                'base_url' => 'http://www.facebook.com/sharer.php?u=',
                'query'    => [
                    't' => ['comment_short', 'heading', 'title'],
                ],
            ],
            'vkontakte'     => [
                'lang' => ['ru'=>['Вконтакте', 'Вконтакте']],
                'base_url' => 'http://vk.com/share.php?url=',
                'query'    => [
                    'title'       => ['heading', 'title'],
                    'description' => ['comment', 'description', 'comment_short'],
                    'image'       => 'image',
                ],
            ],
            'twitter'       => [
                'lang' => ['ru'=>['Твиттер', 'в Твиттере']],
                'base_url' => 'http://twitter.com/share?url=',
                'query'    => [
                    'text' => ['comment_short', 'heading', 'title'],
                ],
            ],
            'linkedin'      => [
                'lang' => ['ru'=>['Линкедин', 'на Линкедине']],
                'base_url' => 'http://www.linkedin.com/shareArticle?mini=true&url=',
                'query'    => [],
            ],
            'googleplus'    => [
                'lang' => ['ru'=>['Гуглплюс', 'на Гуглоплюсе']],
                'base_url' => 'https://plus.google.com/share?url=',
                'query'    => [],
            ],
            'livejournal'   => [
                'lang' => ['ru'=>['Живой журнал', 'в ЖЖ']],
                'base_url' => 'http://www.livejournal.com/update.bml?event=',
                'query'    => [
                    'subject' => ['heading', 'title', 'comment_short'],
                ],
            ],
            'pinterest'     => [
                'lang' => ['ru'=>['Пинтрэст', 'на Пинтрэсте']],
                'base_url' => '//pinterest.com/pin/create/button/?url=',
                'query'    => [
                    'description' => ['description', 'comment', 'comment_short'],
                    'media'       => 'image',
                ],
            ],
            'odnoklassniki' => [
                'lang' => ['ru'=>['Одноклассники', 'в Одноклассниках']],
                'base_url' => '/http://www.odnoklassniki.ru/dk?st.cmd=addShare&st._surl=',
                'query'    => [
                    'title' => ['comment_short', 'heading', 'title'],
                ],
            ],
        ];
        return (empty($socials))
            ? $null_value
            : $socials[$social];
    }


    static function get_title_for_social($social, $num = 1, $lang = self::DEFAULT_LANGUAGE) {
        $soc = self::get_socials_data($social);
        if (!$soc)
            return '';
        return
            $soc['lang'][$lang][$num];
    }


    private static function cond($data, $prefix, $fields, $suffix='', $url_encode = true) {
        $result = '';
        if (is_string($fields))
            $fields = [$fields];

        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                if ($url_encode)
                    $result = $prefix . urlencode($data[$field]) . $suffix;
                else
                    $result = $prefix . $data[$field] . $suffix;
                break;
            }
        }
        return $result;
    }


    /**
     * generate_share_url
     * @param string        $social
     * @param array         $data
     * @return string
     */
    static function generate_share_url($social, array $data) {
        if (empty($data['url']))
            return '';
        $social_data = self::get_socials_data($social);
        if (!$social_data)  return '';

        $query = $data['url'];
        foreach ($social_data['query'] as $key => $val)
            $query .= self::cond( $data, "&{$key}=",  $val );

        $url = $social_data['base_url'] . $query;

        return $url;
    }


    /**
     * generate_share_link
     *
     * @param string    $social - name of social
     * @param array     $data   - share_options
     * @param array     $attrs  - share_options
     * @param string    $inner  - what stays inside
     * @param bool|true $print_title
     *
     * @return string
     */
    static function generate_share_link($social, $data, $attrs=[], $inner='', $print_title=true) {
        $url = self::generate_share_url($social, $data);

        $str = "<a href='{$url}'";

        if ($attrs) {
            foreach ($attrs as $attr_name=>$attr_value)
                $str .= " $attr_name='$attr_value'";
        }

        if (empty($attrs['title']) && $print_title) {
            $title = self::get_title_for_social($social);
            $str .= " title='Поделиться {$title}'";
        }

        $str .= ">{$inner}</a>";
        return $str;
    }





    /** @see https://gist.github.com/davydovanton/9648802  from here*/
    //http://www.facebook.com/sharer.php?u={url}&t={title}
    //http://www.liveinternet.ru/journal_post.php?action=n_add&cnurl={url}&cntitle={title}
    //http://www.livejournal.com/update.bml?event={url}&subject={title}
    //http://connect.mail.ru/share?url={url}&title={title}
    //http://www.odnoklassniki.ru/dk?st.cmd=addShare&st._surl={url}&title={title}
    //http://twitter.com/share?text={title}&url={url}
    //http://vkontakte.ru/share.php?url={url}
    //http://www.google.com/buzz/post?message={title}&url={url}
    //http://del.icio.us/post?url={url}&title={title}
    //http://digg.com/submit?url={url}&title={title}&media=news&topic=people&thumbnails=0
    //http://reddit.com/submit?url={url}&title={title}
    //http://www.technorati.com/faves?add={url}
    //http://share.yandex.ru/go.xml?service=yaru&url={url}&title={title}
    //http://share.yandex.ru/go.xml?service=lj&url={url}&title={title}
    //http://share.yandex.ru/go.xml?service=twitter&url={url}&title={title}
    //http://share.yandex.ru/go.xml?service=facebook&url={url}&title={title}
    //http://share.yandex.ru/go.xml?service=vkontakte&url={url}&title={title}
    //http://pinterest.com/pin/create/button/?url={url}


    /* @see https://developers.facebook.com/docs/reference/opengraph#object-type */

    // =  article
    //    books.author
    //    books.book
    //    books.genre
    //    business.business
    //    fitness.course
    //    game.achievement
    //    music.album
    //    music.playlist
    //    music.radio_station
    //    music.song
    //    place
    //    product
    //    product.group
    //    product.item
    // =  profile
    //    restaurant.menu
    //    restaurant.menu_item
    //    restaurant.menu_section
    //    restaurant.restaurant
    //    video.episode
    //    video.movie
    //    video.other
    //    video.tv_show

    //    article:published_time (datetime) — дата публикации статьи.
    //    article:modified_time ( datetime) — дата последнего изменения статьи.
    //    article:expiration_time (datetime) — дата, после которой статья считается устаревшей.
    //    article:author (profile, массив) — автор статьи.
    //    article:section (string)— тема (раздел), к которой относится статья (например, Технологии).
    //    article:tag (string, массив) — теги (слова, фразы), связанные с этой статьей.
    //
    //    book:author (profile, массив) — автор книги.
    //    book:isbn (string) — уникальный номер книги (ISBN).
    //    book:release_date (datetime) — дата публикации книги.
    //    book:tag (string, массив) — теги (слова, фразы), связанные с этой книгой.
    //
    //    profile:first_name (string) — имя.
    //    profile:last_name (string) — фамилия.
    //    profile:username (string) — ник (имя пользователя, под которым он зарегистрирован).
    //    profile:gender (enum) — пол (male, female).

    //    video:actor (profile, массив) — актеры.
    //    video:actor:role (string) — роли, которые исполняют актеры.
    //    video:director (profile, массив) — режиссер.
    //    video:writer (profile, массив) — сценарист.
    //    video:duration (integer >=1) — длительность фильма в секундах.
    //    video:release_date (datetime) — дата выхода фильма в прокат.
    //    video:tag (string, массив) — теги (слова, фразы), связанные с фильмом.




    //        switch ($social) {
    //            case 'facebook' :
    //                $base_url = 'http://www.facebook.com/sharer.php?u=';
    //                $query = "{$url}";
    //                $query .= self::cond($data, '&' . "t"   . '=', ['heading', 'title', 'comment_short']);
    //                break;
    //            case 'vkontakte' :
    //                $base_url = 'http://vk.com/share.php?url=';
    //                $query = "{$url}";
    //                $query .= self::cond($data, '&' . "title"       .'=', ['heading', 'title']);
    //                $query .= self::cond($data, '&' . "description" .'=', ['comment', 'description', 'comment_short']);
    //                $query .= self::cond($data, '&' . "image"       .'=', 'image');
    //                break;
    //            case 'twitter' :
    //                $base_url = 'http://twitter.com/share?url=';
    //                $query = "{$url}";
    //                $query .= self::cond($data, '&' . "text"       .'=', ['comment_short', 'heading', 'title']);
    //                break;
    //            case 'linkedin' :
    //                $base_url = 'http://www.linkedin.com/shareArticle?mini=true&url=';
    //                $query = "{$url}";
    //                break;
    //            case 'googleplus' :
    //                $base_url = 'https://plus.google.com/share?url=';
    //                $query = "{$url}";
    //                break;
    //            case 'livejournal' :
    //                $base_url = 'http://www.livejournal.com/update.bml?event=';
    //                $query = "{$url}";
    //                $query .= self::cond($data, '&' . "subject"   . '=', ['heading', 'title', 'comment_short']);
    //                break;
    //            case 'pinterest' :
    //                $base_url = '//pinterest.com/pin/create/button/?url=';
    //                $query = "{$url}";
    //                $query .= self::cond($data, '&' . "description".'=', ['description', 'comment', 'comment_short']);
    //                $query .= self::cond($data, '&' . "media"      .'=', 'image');
    //                break;
    //            case 'odnoklassniki' :
    //                $base_url = '/http://www.odnoklassniki.ru/dk?st.cmd=addShare&st._surl=';
    //                $query = "{$url}";
    //                $query .= self::cond($data, '&' . "title" .'=', ['comment_short', 'heading', 'title']);
    //                break;
    //        }
    //
    //        $url = "{$base_url}{$query}";

}
