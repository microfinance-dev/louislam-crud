<?php
namespace LouisLam\CRUD;

use League\Plates\Engine;
use LouisLam\Auth;
use LouisLam\Util;
use Slim\Slim;

/**
 * Class SlimLouisCRUD
 * Created by PhpStorm.
 * User: Louis Lam
 * Date: 8/13/2015
 * Time: 10:37 AM
 * @package LouisLam\CRUD
 */
class SlimLouisCRUD extends LouisCRUD
{

    protected $checkLogin = false;

    private $groupName;
    private $apiGroupName;

    /** @var Slim */
    private $slim;

    /** @var callable */
    protected $configFunction;

    /** @var callable */
    protected $listviewFunction = null;

    /** @var callable */
    protected $createFunction = null;

    /** @var callable */
    protected $editFunction = null;

    /** @var callable */
    protected $deleteFunction = null;

    /** @var callable */
    protected $exportFunction = null;

    /** @var string[] */
    private $tableList = [];
    private $routeNameList = [];
    private $tableDisplayName = [];

    private $currentRouteName = "";

    protected $firstPageURL = "";


    /**
     * SlimCRUD constructor.
     * @param string $groupName
     * @param string $apiGroupName
     * @param Slim $slim
     */
    public function __construct($groupName = "crud", $apiGroupName = "api", $slim = null)
    {
        session_start();

        parent::__construct();
        $this->groupName = $groupName;
        $this->apiGroupName = $apiGroupName;

        if ($slim == null) {
            $this->slim = new Slim();
        } else {
            $this->slim = $slim;
        }

        // Upload function
        $this->slim->post("/louislam-crud/upload/:type", function ($type) {
            $result = $this->upload();

            if (isset($_GET["fullpath"]) && $_GET["fullpath"] == "no") {

            } else {
                $result["url"] = \LouisLam\Util::fullURL($result["url"]);
            }



            if ($type == "js") {
                $url = $result["url"];

                if ($result["uploaded"]) {
                    echo <<< HTML
<script type="text/javascript">
    window.parent.CKEDITOR.tools.callFunction("0", "$url", "");
</script>
HTML;
                } else {
                    $msg = $result["msg"];

                        echo <<< HTML
<script type="text/javascript">
    alert("$msg");
</script>
HTML;
                }



            } else {
                $this->enableJSONResponse();
                echo json_encode($result);
            }

        });

        // Upload Image function
        $this->slim->post("/louislam-crud/upload-image/:type", function ($type) {
            $result = $this->uploadImage("upload", "upload/", 1000);

            if (isset($_GET["fullpath"]) && $_GET["fullpath"] == "no") {

            } else {
                $result["url"] = \LouisLam\Util::fullURL($result["url"]);
            }



            if ($type == "js") {
                $url = $result["url"];

                if ($result["uploaded"]) {
                    echo <<< HTML
<script type="text/javascript">
    window.parent.CKEDITOR.tools.callFunction("0", "$url", "");
</script>
HTML;
                } else {
                    $msg = $result["msg"];

                    echo <<< HTML
<script type="text/javascript">
    alert("$msg");
</script>
HTML;
                }



            } else {
                $this->enableJSONResponse();
                echo json_encode($result);
            }

        });

        $this->slim->get("/auth/login", function () {
            echo $this->getTemplateEngine()->render("adminlte::login");
        });


        $app = $this->slim;

        $this->slim->post("/auth/login", function () use ($app) {
            $result=  Auth::login($_POST["username"], $_POST["password"]);

            if ($result) {

                if (isset($_SESSION["redirect"])) {
                    $app->redirect(Util::fullURL($_SESSION["redirect"]) );
                } else {
                    $app->redirect($this->getFirstPageURL());
                }


            } else {
                $app->redirect(Util::fullURL("auth/login"));
            }
        });

        $this->slim->get("/auth/logout", function () use ($app) {
            Auth::logout();
            $app->redirect(Util::fullURL("auth/login"));
        });




    }

    private function init($tableName, $routeName, $p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) {
        // Table Name set this time ONLY.
        $this->setTable($tableName);
        $this->setTableDisplayName($this->tableDisplayName[$routeName]);

        $this->currentRouteName = $routeName;

        $params = "";

        for ($i = 1; $i <= 5; $i++) {
            $paramName = "p$i";

            if ($$paramName != null) {
                $params .= "/$p1";
            } else {
                break;
            }
        }

        // WEB UI Url
        $this->setListViewLink(Util::url($this->groupName . "/" . $routeName . "/list" . $params));
        $this->setCreateLink(Util::url($this->groupName . "/" . $routeName . "/create" . $params));
        $this->setEditLink(Util::url($this->groupName . "/" . $routeName . "/edit/:id" . $params));
        $this->setCreateSuccURL($this->getListViewLink());

        // Export URL
        $this->setExportLink(Util::url($this->groupName . "/" . $routeName . "/export" . $params));

        // API Url
        $this->setCreateSubmitLink(Util::url($this->apiGroupName . "/" . $routeName));
        $this->setListViewJSONLink(Util::url($this->apiGroupName . "/" . $routeName . "/datatables" . $params));
        $this->setEditSubmitLink(Util::url($this->apiGroupName . "/" . $routeName . "/:id"));
        $this->setDeleteLink(Util::url($this->apiGroupName . "/" . $routeName . "/:id"));

    }


    /**
     * @param Route $route
     */
    public function addRoute($route)
    {
        $route->setCRUD($this);
        $route->addToCRUD();
    }

    /**
     * @param string $routeName
     * @param string $tableName
     * @param callable $customCRUDFunction
     * @param string $displayName
     */
    public function add($routeName, $customCRUDFunction = null, $tableName = null, $displayName = null)
    {

        if ($tableName == null) {
            $tableName = $routeName;
        }

        $this->tableList[$routeName] = $tableName;
        $this->tableDisplayName[$routeName] = $displayName;
        $this->routeNameList[] = $routeName;

        /*
         * Page Group (ListView, CreateView, EditView)
         */
        $this->slim->group("/" . $this->groupName . "/" . $routeName, function () use ($routeName, $customCRUDFunction, $tableName) {

            $this->slim->get("/", function () use ($routeName)  {
                $this->slim->redirectTo("_louisCRUD_" . $routeName);
            });

            /*
             * ListView
             */
            $this->slim->get("/list(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {


                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }



                if ($this->listviewFunction != null) {
                    $listviewFunction = $this->listviewFunction;
                    $result = $listviewFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->isEnabledListView()) {
                    $this->renderListView();
                }

            })->name("_louisCRUD_" . $routeName);

            /*
             * Create
             */
            $this->slim->get("/create(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {

                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->createFunction != null) {
                    $createFunction = $this->createFunction;
                    $result = $createFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                // Force Hide ID field
                $this->field("id")->hide();

                if ($this->isEnabledCreate()) {
                    $this->renderCreateView();
                }
            });

            /*
             * Edit
             */
            $this->slim->get("/edit/:id(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($id, $p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {

                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                // Load Bean first
                $this->loadBean($id);

                // ID must be hidden
                $this->field("id")->hide();

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->editFunction != null) {
                    $editFunction = $this->editFunction;
                    $result = $editFunction($id, $p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                // If user show the ID field, force set it to readonly
                $this->field("id")->setReadOnly(true);

                if ($this->isEnabledEdit()) {
                    $this->renderEditView();
                }
            });

            /*
             * Export Excel
             */
            $this->slim->map("/export(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {

                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->listviewFunction != null) {
                    $listviewFunction = $this->listviewFunction;
                    $result = $listviewFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->exportFunction != null) {
                    $exportFunction = $this->exportFunction;
                    $result = $exportFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                // TODO: isEnabledExport();
                $this->renderExcel();

            })->via('GET', 'POST');

        });

        /*
         * API Group, RESTful style.
         */
        $this->slim->group("/" . $this->apiGroupName . "/" . $routeName, function () use ($routeName, $customCRUDFunction, $tableName) {

            /*
             * JSON for Listview
             */
            $this->slim->map("/list(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {
                $this->enableJSONResponse();

                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->listviewFunction != null) {
                    $listviewFunction = $this->listviewFunction;
                    $result = $listviewFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->isEnabledListView()) {
                    $this->getJSONList();
                }
                return;
            })->via('GET', 'POST');

            /*
             * For Datatables
             */
            $this->slim->map("/datatables(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {
                $this->enableJSONResponse();

                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->listviewFunction != null) {
                    $listviewFunction = $this->listviewFunction;
                    $result = $listviewFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->isEnabledListView()) {
                    $this->getListViewJSONString();
                }
                return;
            })->via('GET', 'POST');


            /*
         * View a bean
         * PUT /api/{tableName}/{id}
         */
            $this->slim->get("/:id(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($id, $p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {

                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                // Load Bean
                $this->loadBean($id);

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                // Custom Global Function
                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                // Custom Edit Function
                if ($this->editFunction != null) {
                    $editFunction = $this->editFunction;
                    $result = $editFunction($id, $p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                // Force hide ID
                $this->field("id")->hide();

                // Insert into database
                if ($this->isEnabledEdit()) {


                   $json = $this->getJSON(false);

                    $this->enableJSONResponse();
                    echo $json;
                }
            });

            /*
             * Insert a bean
             * POST /api/{tableName}
             */
            $this->slim->post("(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {

                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                // Custom Global Function
                $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                if ($result === false) {
                    return;
                }

                // Custom Create Function
                if ($this->createFunction != null) {
                    $createFunction = $this->createFunction;
                    $result = $createFunction($p1, $p2, $p3, $p4, $p5);
                }

                if ($result === false) {
                    return;
                }

                // Force hide ID
                $this->field("id")->hide();

                // Insert into database
                if ($this->isEnabledCreate()) {
                    $jsonObject = $this->insertBean($_POST);

                    $this->enableJSONResponse();
                    echo json_encode($jsonObject);
                } else {
                    // TODO: Should be json object
                    echo "No permission";
                }

            });

            /*
             * Update a bean
             * PUT /crud/{tableName}/{id}
             */
            $this->slim->put("/:id(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($id, $p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {

                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                // Load Bean
                $this->loadBean($id);

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                // Custom Global Function
                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                // Custom Create Function
                if ($this->editFunction != null) {
                    $editFunction = $this->editFunction;
                    $result = $editFunction($id, $p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                // Force hide ID
                $this->field("id")->hide();

                // Insert into database
                if ($this->isEnabledEdit()) {
                    $jsonObject = $this->updateBean($this->slim->request()->params());

                    $this->enableJSONResponse();
                    echo json_encode($jsonObject);
                }
            });

            /*
             * Delete a bean
             * DELETE /crud/{tableName}/{id}
             */
            $this->slim->delete("/:id(/:p1(/:p2(/:p3(/:p4(/:p5)))))", function ($id, $p1 = null, $p2 = null, $p3 = null, $p4 = null, $p5 = null) use ($routeName, $customCRUDFunction, $tableName) {

                // MUST INIT FIRST
                $this->init($tableName, $routeName, $p1, $p2, $p3, $p4, $p5);

                $this->enableJSONResponse();

                $this->loadBean($id);

                if ($this->configFunction != null) {
                    $function = $this->configFunction;
                    $result = $function();

                    if ($result === false) {
                        return;
                    }
                }

                // Custom Global Function
                if ($customCRUDFunction != null) {
                    $result = $customCRUDFunction($p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                // Custom Delete Function
                if ($this->deleteFunction != null) {
                    $deleteFunction = $this->deleteFunction;
                    $result =  $deleteFunction($id, $p1, $p2, $p3, $p4, $p5);

                    if ($result === false) {
                        return;
                    }
                }

                if ($this->isEnabledDelete()) {
                    $this->deleteBean();

                    $result = new \stdClass();
                    $result->status = "succ";

                    echo json_encode($result);
                }

            });

        });

    }

    /**
     * @param callable $func
     */
    public function config($func) {
        $this->configFunction = $func;
    }

    /**
     * @param callable $func
     */
    public function listView($func)
    {
        $this->listviewFunction = $func;
    }

    /**
     * @param callable $func
     */
    public function create($func)
    {
        $this->createFunction = $func;
    }

    /**
     * @param callable $func
     */
    public function edit($func)
    {
        $this->editFunction = $func;
    }

    /**
     * @param callable $func
     */
    public function delete($func)
    {
        $this->deleteFunction = $func;
    }

    /**
     * @param callable $func
     */
    public function export($func)
    {
        $this->exportFunction = $func;
    }

    /**
     * @return Slim
     */
    public function getSlim()
    {
        return $this->slim;
    }

    public function run()
    {
        $this->enableMenu();
        $this->slim->run();
    }

    /**
     * Please make sure you return a valid JSON.
     */
    public function enableJSONResponse()
    {
        $this->slim->response->header('Content-Type', 'application/json');
    }

    /**
     * @return string
     * @deprecated
     */
    public function generateMenu() {
        $temp = "<nav><ul>";

        foreach ($this->routeNameList as $routeName) {
            $url = $this->slim->urlFor("_louisCRUD_" . $routeName);
            $temp .= "<li><a href='$url'>$routeName</a></li>";
        }

        $temp .= "</ul></nav>";
        return $temp;
    }

    public function getMenuItems() {
        $tempList = [];

        foreach ($this->routeNameList as $routeName) {
            $item = [];
            $item["url"] = $this->slim->urlFor("_louisCRUD_" . $routeName);
            $item["name"] = $this->getTableDisplayName($routeName);
            $item["routeName"] = $routeName;
            $tempList[] = $item;
        }
        return $tempList;
    }

    public function enableMenu($menuItems = []) {
        $plates = $this->getTemplateEngine();

        if ($plates->exists("backend_menu")) {
            $name = "backend_menu";
        } else {
            $name = $this->getFullViewName("menu");
        }

        $menu = $plates->render($name, [
            "menuItems" => array_merge($this->getMenuItems(), $menuItems)
        ]);

        $this->setData("menu", $menu);
    }

    /**
     * $crud->url("user", ["male", "1970"]);
     * @param $routeName
     * @param array $data
     * @return string
     */
    public function url($routeName, $data = []) {
        $data2 = [];
        $i = 1;

        // Map key (p1, p2, p3....)
        foreach ($data as $value) {
            $data2["p" . $i++] = $value;
        }

        return $this->slim->urlFor("_louisCRUD_" . $routeName, $data2);
    }

    public function getTableDisplayName($routeName = null) {
        if (isset($this->tableDisplayName[$routeName] ) && $this->tableDisplayName[$routeName] != null) {
            return $this->tableDisplayName[$routeName];
        } else {
            return Util::displayName($routeName);
        }
    }

    /**
     * Override render Excel function
     * @throws Exception\NoFieldException
     */
    public function renderExcel()
    {
        $this->beforeRender();
        $list = $this->getListViewData();

        $helper = new ExcelHelper();
 
        $helper->setHeaderClosure(function ($key, $value) {
            $this->getSlim()->response()->header($key, $value);
        });

        $helper->genExcel($this, $list, $this->getExportFilename());
    }

    /**
     * Content Page
     * @param $route
     * @param callable $callback
     */
    public function page($route, $callback) {
        if ($this->configFunction != null) {
            $function = $this->configFunction;
            $result = $function();

            if ($result === false) {
                return;
            }
        }

        $crud = $this;

        $content = $callback();
        $this->getSlim()->get($route, function () use ($content, $crud) {
            $this->render($crud->getThemeName() . "::page", [
                "content" => $content
            ], true);
        });
    }

    public function notFound() {
        $this->getSlim()->notFound();
    }

    public function checkLogin() {
        $crud = $this;

        Auth::checkLogin(function () use ($crud) {

            // Get request object
            $req = $crud->getSlim()->request;

//Get root URI
            $rootUri = $req->getRootUri();

//Get resource URI
            $resourceUri = $req->getResourceUri();

            $_SESSION["redirect"] = $resourceUri;
           $crud->getSlim()->redirect(Util::fullURL("auth/login"));
        });
    }

    /**
     * @return string
     */
    public function getFirstPageURL()
    {
        return $this->firstPageURL;
    }

    /**
     * @param string $firstPageURL
     */
    public function setFirstPageURL($firstPageURL)
    {
        $this->firstPageURL = $firstPageURL;
    }


}