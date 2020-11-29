<?php

namespace Sitemap\Controller;

use Cake\Utility\Hash;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Sitemap\Controller\AppController;

class SitemapsController extends AppController
{
    public $_allowedTags = [
      'loc' => true,
      'lastmod' => true,
      'priority' => true,
      'changefreq' => true,
    ];

    public $_defaultConfig = [
        'loc' => 'url',
        'lastmod' => 'updated',
        'priority' => '0.5',
        'changefreq' => 'daily',
    ];

    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('RequestHandler');
    }

    /**
     * Allow the display function if the Auth component is loaded
     */
    public function beforeFilter(EventInterface $event)
    {
        if (isset($this->Auth)) :
            $this->Auth->allow('display');
        endif;
    }

    public function display()
    {
        $this->viewBuilder()->setLayout('Sitemap.sitemap');

        $config = Configure::read('Sitemap');

        $pages = [];

        # build static page sitemap lines
        foreach ($config['static'] as $static) :
            $page = [];
            $page['loc'] = Router::url($static + ['plugin'=>false], $fullBase = true);
            $page['priority'] = '0.5';
            $page['changefreq'] = 'weekly';
            $pages[] = $page;
        endforeach;

        # build the dynamic page sitemap lines
        foreach ($config['dynamic'] as $modelName => $options) :
            $c = Hash::merge($this->_defaultConfig, $options['xmlTags']);

            $model = $this->getTableLocator()->get($modelName);

            # build the model query using the finders
            $data = $model->find();
            foreach ($options['finders'] as $finder => $finderParams) :
                $data = $data->find($finder, $finderParams);
            endforeach;

            # cache - if exists
            if (isset($options['cacheKey'])) :
                $data->cache($modelName, $options['cacheKey']);
            endif;

            # loop through the found data and build the sitemap lines
            foreach ($data as $entity) :
                if(is_array($c['loc'])){
                    $url = '';
                    if(isset($c['loc']['urlBody'])){
                        $url = $c['loc']['urlBody'];
                    }
                    if(!empty($c['loc']['queryParams']) && is_array($c['loc']['queryParams'])){
                        $params = [];
                        foreach ($c['loc']['queryParams'] as $key => $queryParam) {
                            $params[$key] = is_object($entity) ? $entity->get($queryParam) : $entity[$queryParam];
                        }
                        $url .= '?'.http_build_query($params);
                    }
                }
                else{
                    $url = is_object($entity) ? $entity->get($c['loc']) : $entity[$c['loc']];
                    $url = is_array($url) ? $url + ['plugin'=>false] : $url;
                }
                $page = [];
                $page['loc'] = Router::url($url, $fullBase = true);
                $page['priority'] = $c['priority'];
                $page['changefreq'] = $c['changefreq'];
                $pages[] = $page;
            endforeach;
        endforeach;
        
        $this->set(compact('pages'));
        $this->set('_serialize', false);
    }
}
