<?php
namespace Drupal\parramatta_global\Breadcrumb;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
// Use namespaces of classes that you need
use Drupal\Core\Breadcrumb\Breadcrumb;


class EventBreadcrumbBuilder implements BreadcrumbBuilderInterface {
    use StringTranslationTrait;


    /**
     * {@inheritdoc}
     */
    public function applies(RouteMatchInterface $route_match) {
        // You can put any logic here. You must return a BOOLEAN TRUE or FALSE.
        //-----[ BEGIN example ]-----

        // Determine if the current page is a node page
        /** @var \Drupal\node\Entity\Node $node */
        $node = $route_match->getParameter('node');
        if ($node && $node->getType() == 'events') {
            // You can do additional checks here for the node type, etc...
            return TRUE;
        }
        //-----[ END example ]-----

        // Still here? This does not apply.
        return FALSE;
    }


    /**
     * @inheritdoc
     */
    public function build(RouteMatchInterface $route_match) {
        // Get the node for the current page
        /** @var \Drupal\node\Entity\Node $node */
        $node = $route_match->getParameter('node');
        $breadcrumb = new Breadcrumb();
        $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

        // If there is a Parent page that is the prev BC OR else What's on
        if(!is_null($node->parent_page->entity)) {
            $parent_node = $node->parent_page->entity;
            //$parent_node_id = $this->getSimpleArrayFromMultiValueFieldArray($node->get('parent_page')->getValue(), 'target_id');
            $parent_node_url = Url::fromRoute('entity.node.canonical', array('node' => $parent_node->id()));
            $breadcrumb->addLink(Link::fromTextAndUrl($this->t($parent_node->getTitle()), $parent_node_url));

        } else {
            $url = Url::fromUri('internal:/whats-on');
            $breadcrumb->addLink(Link::fromTextAndUrl($this->t('What\'s On'), $url));
        }

        // Current title
        $breadcrumb->addLink(Link::createFromRoute($node->getTitle(), '<none>'));


        // Don't forget to add cache control by a route.
        // Otherwise all pages will have the same breadcrumb.
        $breadcrumb->addCacheContexts(['route']);
        return $breadcrumb;
    }

    /**
     * Convert value areray to simple array
     * @param $multi_value_field_array
     *
     * @return array
     */
    private function getSimpleArrayFromMultiValueFieldArray($multi_value_field_array, $key = 'value') {
        $return_array = [];
        foreach ($multi_value_field_array as $val) {
            $return_array[] = $val[$key];
        }
        return $return_array;
    }
}