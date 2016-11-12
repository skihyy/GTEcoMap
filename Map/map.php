<!DOCTYPE html>
<meta charset="utf-8">
<head>
    <script src="map.js"></script>
    <link rel="stylesheet" type="text/css" href="map.css">
    <title>Sustainability Map</title>
    <link rel="shortcut icon" type="image/x-icon" href="titleIcon.ico"/>
</head>
<!-- after the page is loaded, if some buttons were blue, they should trun to blue again. -->
<body onload="">
<?php
/**
 * Created by PhpStorm.
 * User: Yuyang He
 * Date: 2016/11/07
 * Time: 下午 04:41
 */

// not showing notices or errors
error_reporting(E_ALL ^ E_NOTICE);

// basic MySQL connection preparation
$con = mysqli_init();

if (!$con) {
    die("Database connection initialization failed.");
}

if (!mysqli_real_connect($con, "127.0.0.1", "root", "root", "gt_eco_map", 3306)) {
    die("Connect Error: " . mysqli_connect_error());
}

$allAreas = queryAllSustainAreas($con);
$areaFilterSelected = [];

// if one filters something
if (!is_null($_REQUEST["todo"])) {
    $areaFilterSelected = $_REQUEST["area"];
}

$projectList = queryProject($areaFilterSelected, $con);
$peopleList = queryPropleBasedOnProjects($projectList, $con);

printingPage($areaFilterSelected, $allAreas);

////////////////////////////////////////////////////////////////////
/////////////////            FUNCTIONS                //////////////
////////////////////////////////////////////////////////////////////

/**
 * Printing the web pages. Of course, the map will be printed by JS.
 */
function printingPage($areaFilterSelected, $allAreas)
{
    printTitle();
    printFilters($areaFilterSelected, $allAreas);
    printMapAreas();
}

/**
 * Printing the title and description of the web page.
 */
function printTitle()
{
    ?>
    <div id="tableTitle" class="div title">
        <p id="tableTitleText" class="p title">Campus Sustainability Interactive Map</p>
    </div>

    <div id="tableDescription" class="div description">
        <p id="tableDescriptionText1" class="p description">
            Campus sustainability interactive map connects between sustainability areas, sustainability projects, and
            people, who are involved in these projects on GT campus.
        </p>
    </div>
    <?php
}

/**
 * Printing the areas for the map.
 */
function printMapAreas()
{
    ?>
    <div class="div map" id="mapDiv">
        <div class="map div title">
            Sustainability Projects
        </div>
        <div class="map div d3Area"></div>
    </div>
    <?php
}

/**
 * Printing the filters.
 */
function printFilters($areaFilterSelected, $allAreas){
?>
<div class="filters div" id="filterDiv">
    <div class="filters div title">
        Sustainability Area
    </div>
    <div class="filters div area" id="sustainSelectAlldiv">
        <input class="input filter selectAll content-detail" id="sustainSelectAll" value="" type="checkbox"
               onclick="selectAll(this, 'areaChkList')"
            <?php
            if (count($areaFilterSelected) == count($allAreas)) {
                echo " checked='checked'";
            }
            ?>
        >
        <label for="sustainSelectAll0" class="content-detail">All</label>
    </div>
    <?php
    foreach ($allAreas as $area) {
        ?>
        <div class="div area filter" id="area<?php echo $area["id"]; ?>">
            <div class="div area filter chkbox" id="areaChkbox<?php echo $area["id"]; ?>">
                <input class="input area filter" id="input<?php echo $area["id"]; ?>"
                       value="<?php echo $area["id"]; ?>" type="checkbox" name="areaChkList"
                       onclick="checkSelectAll('sustainSelectAll', 'areaChkList')"
                    <?php
                    if (contain($areaFilterSelected, $area["id"])) {
                        echo " checked='checked'";
                    }
                    ?>
                >
                <label class="label filter area"><?php echo $area["name"]; ?>&nbsp&nbsp&nbsp</label>
            </div>
            <div class='colorBlock' style='background-color:<?php echo $area["color"]; ?>;
                float: left'>
                &nbsp&nbsp&nbsp
            </div>
            <br>
        </div>
        <?php
    }
    ?>
</div>
<?php
}

/**
 * Query projects based on sustainability areas.
 * @param $projectAreaID sustainability area ID
 * @param $con database connection
 * @return array a list of projects
 */
function queryProject($projectAreaID, $con)
{
    $query = "SELECT * FROM project ";

    if (!is_null($projectAreaID)) {
        $query .= " WHERE ";
        foreach ($projectAreaID as $aid) {
            $query .= " area = " . $aid . " ";
        }
    }

    $query .= ";";

    $queryResult = $con->query($query);

    if (false != $queryResult) {
        $result = [];
        while ($row = $queryResult->fetch_array()) {
            array_push($result, ["id" => $row["id"], "name" => $row["name"],
                "area" => $row["area"], "uid" => explode(",", $row["uID"]),
                "link" => $row["link"]]);
        }
    }

    return $result;
}

/**
 * Given a list of projects, return all related people.
 * @param $projectList $projectList project list from DB retrieve, @see queryProject
 * @param $con database connection
 * @return array|null a list of people if $projectList is not null, otherwise null
 */
function queryPropleBasedOnProjects($projectList, $con)
{
    $pid = getPIDFromProjects($projectList);
    $result = null;

    if (!is_null($pid)) {
        $result = [];

        $query = "SELECT * FROM person WHERE ";

        $hasOr = false;

        foreach ($pid as $uid) {
            if (!$hasOr) {
                $query .= " id = " . $uid . " ";
                $hasOr = true;
            } else {
                $query .= " or id = " . $uid . " ";
            }
        }

        $query .= ";";

        $queryResult = $con->query($query);


        while ($row = $queryResult->fetch_array()) {
            array_push($result, ["id" => $row["id"], "deptID" => $row["deptID"],
                "name" => $row["name"], "area" => $row["area"],
                "role" => $row["role"], "phone" => $row["phone"],
                "email" => $row["email"], "pLink" => $row["pLink"]]);
        }
    }

    return $result;
}

/**
 * Given a project list from DB retrieve, return a list of all user IDs without redundant.
 * @param $projectList project list from DB retrieve, @see queryProject
 * @return array|null a list of all user IDs without redundant or null if the project is null
 */
function getPIDFromProjects($projectList)
{
    $pid = null;

    if (!is_null($projectList)) {
        $pid = [];
        foreach ($projectList as $project) {
            $uids = $project["uid"];

            foreach ($uids as $uid) {
                if (!contain($pid, $uid)) {
                    array_push($pid, $uid);
                }
            }
        }
    }

    return $pid;
}

/**
 * Check whether one element in an array has a same object as given.
 * @param $list array to be checked
 * @param $needle given string
 * @return bool true if contains
 */
function contain($list, $needle)
{
    foreach ($list as $item) {
        if ($item == $needle) {
            return true;
        }
    }
    return false;
}

/**
 * Return all area ID and names.
 * @param $con database connection
 * @return array a list of area
 */
function queryAllSustainAreas($con)
{
    $query = "SELECT id, name, color FROM area;";
    $queryResult = $con->query($query);

    $result = [];
    while ($row = $queryResult->fetch_array()) {
        array_push($result, ["id" => $row["id"], "name" => $row["name"], "color" => $row["color"]]);
    }

    return $result;
}