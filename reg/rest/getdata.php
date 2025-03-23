<?php
require_once("config.php");
    $cfg = new Config();

    if(!isset($_POST["func"]))
    {
        echo "No function";
        exit();
    }

    $func = $_POST["func"];	// Function Type
	$cfg = new Config();
	$db = new mysqli($cfg->dbhost,$cfg->dbuser,$cfg->dbpass,$cfg->dbname);
	if($db->connect_errno > 0)
	{
		return 'Unable to connect to database [' . $db->connect_error . ']';
	}

    if($func == "getStudent")
    {   
        $sql = "SELECT distinct rs.Sid,Concat(Title,F_Name,\" \",L_Name) as Name, classId FROM `reg_student` as rs, reg_class_register as rcr WHERE rs.Sid = rcr.sid AND (classId = 416 OR ClassId = 417 OR ClassId = 419) ORDER By ClassId,sid";
        
        $result = $db->query($sql);	
        while($row = $result->fetch_assoc())
		{
			$json_array[] = $row;
		}
        
        //echo "test";
        echo json_encode($json_array);

    }
    else if($func == "getGrade")
    {
        $sid = $_POST["sid"];
        
        $sql = "SELECT rm.SujId,acs.Name,acs.Credit,rg.Name as Grade,rg.Value FROM `reg_master` as rm, aca_subject as acs, reg_grade as rg WHERE Sid = \"" . $sid . "\" AND rm.SujId = acs.SujId AND rm.GradeId = rg.GradeId AND (CourseId = 6 OR CourseId = 5) ORDER By rm.ClassId,rm.SujId";
        
        $result = $db->query($sql);	
        while($row = $result->fetch_assoc())
		{
			$json_array[] = $row;
		}
        
        //echo "test";
        echo json_encode($json_array);
    }
    else if($func == "getStudentCheckSSN")
    {
        $sid = $_POST["Sid"];
        $ssn = $_POST["SSN"];
        
        $sql = "SELECT * FROM reg_student WHERE Sid = \"" . $sid . "\" AND SSN =\"" .$ssn ."\"";
        
        $result = $db->query($sql);	
        while($row = $result->fetch_assoc())
		{
			$json_array[] = $row;
		}
        
        //echo $sql;
        echo json_encode($json_array);
    }
    else
	{
		echo "Invalid function call";
	}
    $db->close();
?>