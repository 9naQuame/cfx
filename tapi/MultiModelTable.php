<?php
class MultiModelTable extends Table
{
    private $fields = array();
    private $itemsPerPage = 10;
    public $useAjax = false;
    protected $params;
    protected $tableData;
    protected $model;

    public function __construct($prefix = null)
    {
        parent::__construct($prefix);
    }

    public function setParams($params)
    {
        $params["limit"] = $this->itemsPerPage;
        $params["offset"] = $this->itemsPerPage * $params["page"];
        $params["url"] = Application::$prefix . "/api/table";
        $params["id"] = $this->name;
        $params["operations"] = $this->operations;
        $params["moreInfo"] = true;
        $this->params = $params;
        $this->tableData = SQLDBDataStore::getMulti($this->params);
    }

    protected function renderHeader()
    {
        $searchFunction = $this->name."Search()";
        $table = "<table class='tapi-table' id='$this->name'>";

        //Render Headers
        $table .= "<thead><tr><td>";
        $table .= "<input type='checkbox' onchange=\"ntentan.tapi.checkToggle('$this->name',this)\"></td>";

        foreach($this->headers as $i => $header)
        {
            $table.="<td onclick=\"ntentan.tapi.sort('".$this->name."','".$this->tableData["rawFields"][$i+1]."')\">
            $header
            </td>";
        }
        $table .= "<td>Operations</td></tr>";


        //Render search fields
        $table .= "<tr id='tapi-$this->name-search' class='tapi-search-row' ><td></td>";

        foreach($this->headers as $i => $header)
        {
            $table.="<td>";
            //if(count(explode(",",$this->fields[$i]))==1)
            //{
                switch($this->fields[$i+1]["type"])
                {
                    case "string":
                    case "text":
                        $text = new TextField();
                        $text->setId($this->fields[$i+1]["name"]);
                        $text->addAttribute("onkeyup",$searchFunction);
                        $table .= $text->render();
                        $name = $this->fields[$i+1]["name"];
                        $this->searchScript .= "if($('#$name').val()!='') conditions = (conditions==''?'':conditions+' AND ')+ \"position(lower('\" + $('#$name').val() +\"') in lower({$this->tableData["rawFields"][$i+1]}))>0\";\n";
                        break;

                    /*case "reference":
                        $text = new TextField();
                        $text->setId($this->fields[$i]["name"]);
                        $text->addAttribute("onkeyup",$searchFunction);
                        $table .= $text->render();
                        $modelInfo = Model::resolvePath($this->fields[$i]["reference"]);
                        $model = Model::load($modelInfo["model"]);
                        $fieldName = $model->database.".".$this->fields[$i]["referenceValue"];
                        $this->searchScript .= "if($('#{$this->fields[$i]["name"]}').val()!='') condition += escape('$fieldName='+$('#{$fields[$i]["name"]}').val()+',');";
                        break;

                        $list = new ModelSearchField($fields[$i]["reference"],$fields[$i]["referenceValue"]);
                        $list->boldFirst = false;
                        $list->setId($fields[$i]["name"]);
                        $list->addAttribute("onChange",$searchFunction);
                        $table .= $list->render();
                        $modelInfo = Model::resolvePath($fields[$i]["reference"]);
                        $model = Model::load($modelInfo["model"]);
                        $fieldName = $model->database.".".$field[$i]["name"];
                        $this->searchScript .= "if($('#{$field["name"]}').val()!='') condition += escape('$fieldName='+$('#{$field["name"]}').val()+',');";
                        break;*/
                    /*case "enum":
                        $list = new SelectionList();
                        foreach($fields[$i]["options"] as $value => $label)
                        {
                            $list->addOption($label,$value);
                        }
                        $list->setId($fields[$i]["name"]);
                        $table.=$list->render();
                        break;
                    case "integer":
                    case "double":
                        $options = Element::create("SelectionList")->
                                    addOption("Equals",0)->
                                    addOption("Greater than",1)->
                                    addOption("Less than",2);
                        $text = new TextField();
                        $text->setId($fields[$i]["name"]);
                        $table .= $options->render().$text->render();
                        break;
                    case "date":
                        $date = new DateField();
                        $date->setId($fields[$i]["name"]);
                        $table .= $date->render();
                        break;
                    case "boolean":
                        $options = Element::create("SelectionList")->
                                    addOption("Yes",1)->addOption("No",0);
                        $options->setId($fields[$i]["name"]);
                        $table .= $options->render();
                        break;*/
                }
            //}
            /*else
            {
                $text = new TextField();
                $text->setId("concat_field_$i");
                $text->addAttribute("onkeyup",$searchFunction);
                $table .= $text->render();
                $name = addslashes($this->model->datastore->concatenate(explode(",",$this->fields[$i])));
                $this->searchScript .= "if($('#concat_field_$i').val()!='') condition += escape('$name='+$('#concat_field_$i').val()+',');";
            }*/
            $table .="</td>";
        }
        $table .= "<td><input class='fapi-button' type='button' value='Search' onclick='$searchFunction'/></td></tr></thead>";

        //Render Data
        $table .= "<tbody id='tbody'>";

        return $table;
    }

    public function render($headers = true)
    {
        $results = $this->tableData;
        $this->fields = $results["fieldInfos"];

        foreach($this->fields as $field)
        {
            if($field["type"] == "number" || $field["type"] == "double" || $field["type"] == "integer")
            {
                $this->headerParams[$field["name"]]["type"] = "number";
            }
        }

        $this->headers = $results["headers"];
        array_shift($this->headers);
        if($headers === true) $table = $this->renderHeader();
        if($this->useAjax)
        {
            $table .= "<tr>
                <td align='center' colspan='".count($this->headers)."'>
                    <span style='color:#909090;font-weight:bold;font-size:24px'>Loading ...</span><br/>
                    <img src='".Application::$prefix."/images/loading-image-big.gif' />
                </td></tr>";
        }
        else
        {
            $this->data = $results["data"];
            $table .= parent::render(false);
        }

        if($headers === true) $table .= $this->renderFooter();

        if($this->useAjax)
        {
            $table .=
            "<script type='text/javascript'>
                ntentan.tapi.addTable('$this->name',(".json_encode($this->params)."));
                var externalConditions = [];
                function {$this->name}Search()
                {
                    var conditions = '';
                    {$this->searchScript}
                    ntentan.tapi.tables['$this->name'].conditions = conditions;
                    if(externalConditions['$this->name'])
                    {
                        ntentan.tapi.tables['$this->name'].conditions += ((conditions != '' ?' AND ':'') + externalConditions['$this->name']);
                    }
                    ntentan.tapi.tables['$this->name'].page = 0;
                    ntentan.tapi.render(ntentan.tapi.tables['$this->name']);
                }
            </script>";
        }
        return $table;
    }

    public function renderFooter()
    {
        $table = parent::renderFooter();
        $params = $this->params;
        $params["count"] = true;
        unset($params["moreInfo"]);
        unset($params["limit"]);
        unset($params["offset"]);
        $data = SQLDBDataStore::getMulti($params);
        $numPages = ceil($data[0]["count"] / $this->itemsPerPage);

        $lastPage = $numPages - 1;
        for($i = 1; $i < /*$numPages*/ 10; $i++)
        {
            $position =  round(log($i, 10) * $numPages);
            $options.="<option value='".$position."' ".($params["page"]==$position?"selected='selected'":"")." >".($position+1)."</option>";
        }

        $table .= "<div id='{$this->name}Footer'>
            <ul class='table-pages'>".
                ($params["page"]>0?
                    "<li>
                        <a onclick=\"ntentan.tapi.switchPage('$this->name',0)\">
                            &lt;&lt; First
                        </a>
                    </li>
                    <li>
                        <a onclick=\"ntentan.tapi.switchPage('$this->name',".($params["page"]-1>=0?$params["page"]-1:"").")\">
                            &lt; Prev
                        </a>
                    </li>":"") .
                ($params["page"]<$lastPage?"<li><a onclick=\"ntentan.tapi.switchPage('$this->name',".($params["page"]+1<=$lastPage?$params["page"]+1:"").")\">Next &gt;</a></li><li><a onclick=\"ntentan.tapi.switchPage('$this->name',$lastPage)\">Last &gt;&gt;</a></li>":"").
                "<li> | </li>
                <li> Page <input style='font-size:small' value = '".($params["page"]+1)."' onchange=\"ntentan.tapi.switchPage('$this->name',(this.value > 0 && this.value < $numPages)?this.value-1:0)\" size='".strlen($numPages)."' type='text' /> of $numPages </li>
                <li> | </li>
                <li>Jump To <select onchange=\"ntentan.tapi.switchPage('$this->name',this.value)\">$options</select></li>

            </ul>
        </div>";
        return $table;
    }
}
