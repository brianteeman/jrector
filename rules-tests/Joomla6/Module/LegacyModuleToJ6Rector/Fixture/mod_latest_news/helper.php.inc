<?php

defined('_JEXEC') or die;

class ModLatestNewsHelper
{
    public static function getItems($params)
    {
        return [];
    }
}
-----
<?php

namespace Acme\Module\LatestNews\Site\Helper;

defined('_JEXEC') or die;
class LatestNewsHelper
{
    public static function getItems($params)
    {
        return [];
    }
}
