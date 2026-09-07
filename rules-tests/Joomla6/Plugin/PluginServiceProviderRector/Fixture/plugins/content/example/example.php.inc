<?php

defined('_JEXEC') or die;

class PlgContentExample extends \Joomla\CMS\Plugin\CMSPlugin
{
    public function onContentPrepare()
    {
    }
}
-----
<?php

namespace Acme\Plugin\Content\Example\Extension;

defined('_JEXEC') or die;
class Example extends \Joomla\CMS\Plugin\CMSPlugin
{
    public function onContentPrepare()
    {
    }
}
