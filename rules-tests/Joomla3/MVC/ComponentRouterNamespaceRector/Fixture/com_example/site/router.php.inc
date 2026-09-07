<?php

defined('_JEXEC') or die;

class ExampleRouter extends JComponentRouterBase
{
    public function build(&$query)
    {
        return [];
    }
}
-----
<?php

namespace Acme\Example\Site\Service;

defined('_JEXEC') or die;
class Router extends JComponentRouterBase
{
    public function build(&$query)
    {
        return [];
    }
}
