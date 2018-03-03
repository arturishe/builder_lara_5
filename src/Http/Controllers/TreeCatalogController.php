<?php

namespace Vis\Builder;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

class TreeCatalogController
{
    protected $model;
    protected $options;
    protected $nameTree;
    protected $controller;

    public function __construct($model, array $options, $nameTree)
    {
        $this->model = $model;
        $this->options = $options;
        $this->nameTree = $nameTree;

        $this->controller = new JarboeController($options);
    }

    // end __construct

    public function setOptions(array $options = [])
    {
        $this->options = $options;
    }

    // end setOptions

    public function handle()
    {
        switch (Input::get('query_type')) {
            case 'do_create_node':
                return $this->doCreateNode();

            case 'clone_record_tree':
                return $this->doCloneRecord();

            case 'do_change_active_status':
                return $this->doChangeActiveStatus();

            case 'do_change_position':
                return $this->doChangePosition();

            case 'do_delete_node':
                return $this->doDeleteNode();

            case 'do_edit_node':
                return $this->doEditNode();

            case 'do_update_node':
                return $this->doUpdateNode();

            case 'get_edit_modal_form':
                return $this->getEditModalForm();

            default:
                return $this->handleShowCatalog();
        }
    }

    // end handle

    public function doUpdateNode()
    {
        $model = $this->model;

        switch (request('name')) {
            case 'template':
                $node = $model::find(Input::get('pk'));
                $node->template = Input::get('value');
                $node->save();

                $node->clearCache();
                break;

            default:
                throw new \RuntimeException('someone tries to hack me :c');
        }
    }

    // end doUpdateNode

    public function doCreateNode()
    {
        $model = $this->model;

        $root = $model::find(Input::get('node', 1));

        $node = new $model();

        $node->parent_id = request('node', 1);
        $node->title = request('title');
        $node->template = request('template') ?: '';
        $node->slug = request('slug');
        $node->is_active = 1;
        $node->save();

        $node->checkUnicUrl();
        $node->makeChildOf($root);

        $root->clearCache();

        return Response::json([
            'status' => true,
        ]);
    }

    // end doCreateNode

    public function doCloneRecord()
    {
        $model = $this->model;
        $root = $model::find(request('node', 1));

        $id = request('id');

        $this->cloneRecursively($id);

        $root->clearCache();

        $res = [
            'id'     => $id,
        ];

        return $res;
    }

    private function cloneRecursively($id, $parentId = '')
    {
        $model = $this->model;

        $page = $model::where('id', $id)->select('*')->first()->toArray();
        $idClonePage = $page['id'];
        unset($page['id']);
        if ($parentId) {
            $page['parent_id'] = $parentId;
        }

        if ($page['parent_id']) {
            $root = $model::find($page['parent_id']);

            $rec = new $model;

            if ($model::where('parent_id', $page['parent_id'])->where('slug', $page['slug'])->count()) {
                if ($parentId) {
                    $page['slug'] = $page['slug'].'_'.$page['parent_id'];
                } else {
                    $page['slug'] = $page['slug'].'_'.time();
                }
            }

            foreach ($page as $k => $val) {
                $rec->$k = $val;
            }

            $rec->save();
            $lastId = $rec->id;

            $rec->makeChildOf($root);
        }

        $folderCheck = $model::where('parent_id', $idClonePage)->select('*')->orderBy('lft', 'desc')->get()->toArray();
        if (count($folderCheck)) {
            foreach ($folderCheck as $pageChild) {
                $this->cloneRecursively($pageChild['id'], $lastId);
            }
        }
    }

    public function doChangeActiveStatus()
    {
        $model = $this->model;
        $node = $model::find(Input::get('id'));
        $node->is_active = Input::get('is_active') ? '1' : '0';

        $node->save();

        $node->clearCache();

        return Response::json([
            'active' => true,
        ]);
    }

    // end doChangeActiveStatus

    public function doChangePosition()
    {
        $model = $this->model;

        $id = Input::get('id');
        $idParent = Input::get('parent_id', 1);
        $idLeftSibling = Input::get('left_sibling_id');
        $idRightSibling = Input::get('right_sibling_id');

        $item = $model::find($id);
        $root = $model::find($idParent);

        $prevParentID = $item->parent_id;
        $item->makeChildOf($root);

        $item->slug = $item->slug;
        $item->save();

        if ($prevParentID == $idParent) {
            if ($idLeftSibling) {
                $item->moveToRightOf($model::find($idLeftSibling));
            } elseif ($idRightSibling) {
                $item->moveToLeftOf($model::find($idRightSibling));
            }
        }

        $root->clearCache();

        $item = $model::find($item->id);
        $item->checkUnicUrl();

        $data = [
            'status' => true,
            'item' => $item,
            'parent_id' => $root->id,
        ];

        return Response::json($data);
    }

    // end doChangePosition

    public function process()
    {
        $model = $this->model;

        $idNode = Input::get('page_id', Input::get('node', 1));
        $current = $model::find($idNode);

        $templates = Config::get('builder.'.$this->nameTree.'.templates');
        $template = Config::get('builder.'.$this->nameTree.'.default');
        if (isset($templates[$current->template])) {
            $template = $templates[$current->template];
        }

        $options = [
            'url'      => URL::current(),
            'def_name' => $this->nameTree.'.'.$template['node_definition'],
            'additional' => [
                'node'    => $idNode,
                'current' => $current,
            ],
        ];

        return \Jarboe::table($options);
    }

    // end process

    public function doDeleteNode()
    {
        $model = $this->model;

        $model::destroy(Input::get('id'));

        $modelObj = $model::find('1');
        $modelObj->clearCache();

        return Response::json([
            'status' => true,
        ]);
    }

    // end doDeleteNode

    private function handleShowCatalog()
    {
        $parentIDs = [];
        $model = $this->model;
        $treeName = $this->nameTree;
        $controller = $this->controller;

        $idNode = Input::get('node', 1);
        $current = $model::find($idNode);

        foreach ($current->getAncestors() as $anc) {
            $parentIDs[] = $anc->id;
        }

        $children = $current->children();

        //filter ids
        $actions = config('builder.'.$treeName.'.actions.show');

        if ($actions && $actions['check']() !== true && is_array($actions['check']())) {
            $arrIdsShow = $actions['check']();

            foreach ($actions['check']() as $id) {
                $arrIdsShow[] = $model::find($id)->getDescendants()->pluck('id')->toArray();
            }

            $arrIdsShow = array_flatten($arrIdsShow);
            $children = $children->whereIn('id', $arrIdsShow);
        }
        //filter ids end

        $children = $children->paginate(20);

        $templates = config('builder.'.$treeName.'.templates');
        $template = config('builder.'.$treeName.'.default');

        if (isset($templates[$current->template])) {
            $template = $templates[$current->template];
        }

        $content = view('admin::tree.content', compact('current', 'template', 'treeName', 'children', 'controller'));
        $treeView = Request::ajax() ? 'tree_ajax' : 'tree';

        return view('admin::'.$treeView, compact('content', 'current', 'parentIDs', 'treeName', 'controller'));
    }

    public function getEditModalForm()
    {
        $model = $this->model;

        $idNode = Input::get('id');
        $current = $model::find($idNode);

        $templates = config('builder.'.$this->nameTree.'.templates');
        $template = config('builder.'.$this->nameTree.'.default');

        if (isset($templates[$current->template])) {
            $template = $templates[$current->template];
        }

        $options = [
            'url'      => URL::current(),
            'def_name' => $this->nameTree.'.'.$template['node_definition'],
            'additional' => [
                'node'    => $idNode,
                'current' => $current,
            ],
        ];
        $controller = new JarboeController($options);

        $html = $controller->view->showEditForm($idNode, true);

        return Response::json([
            'status' => true,
            'html' => $html,
        ]);
    }

    // end getEditModalForm

    public function doEditNode()
    {
        $model = $this->model;

        $idNode = Input::get('id');
        $current = $model::find($idNode);

        $templates = config('builder.'.$this->nameTree.'.templates');
        $template = config('builder.'.$this->nameTree.'.default');

        if (isset($templates[$current->template])) {
            $template = $templates[$current->template];
        }

        $options = [
            'url'        => URL::current(),
            'def_name'   => $this->nameTree.'.'.$template['node_definition'],
            'additional' => [
                'node'    => $idNode,
                'current' => $current,
            ],
        ];
        $controller = new JarboeController($options);

        $result = $controller->query->updateRow(Input::all());

        $item = $model::find($idNode);
        $item->clearCache();
        $item->checkUnicUrl();
        $treeName = $this->nameTree;
        $result['html'] = view('admin::tree.content_row', compact('item', 'treeName', 'controller'))->render();

        return Response::json($result);
    }

    // end doEditNode
}
