<?php
/*RESTful API for CS4380.
  Created by: Jonathon Lantsberger
  Pawprint: JTL378
  Student ID: 14205953
  Date: 10/1/16
  Base URL to service: https://jonlantsberger.com/challenge2 (must use https!!!)
  */
  include 'db.php'; //seperated out the SQL credentials and connect statement to protect my information
  $url = explode('/', $_SERVER['REQUEST_URI']); //reading in the request URI and splitting it into an array using / as a delimiter
  array_shift($url); //the first elemet of the array is an empty string, so we need to shift it off the array
  $verb = $_SERVER['REQUEST_METHOD']; //setting the verb for REST api

  if($url[1] != 'Teams'){ //checks to make sure the REST url is asking for the correct resource
    header("HTTP/1.0 404 Not Found"); //sets the header to the correct 404 response when URL is incorrect
    include('404.php');
    exit();
  }

  switch ($verb) {
    case 'GET':
      get($url, $conn);
      break;

    case 'DELETE':
      del($conn);
      break;

    case 'PUT':
      put($conn);
      break;

    case 'POST':
      post($url, $conn);
      break;

    default:
      echo "Currently Unsupported.";
      break;
  }

  function post($url, $conn){
    //If this were production code, I would need to make sure I properly escaped all the sql queries to avoid SQL injecton, but for this assignment it is unnecessary
    $request = file_get_contents('php://input'); //reading in the passed json then decoding it on the next line
    $arr = json_decode($request);

    if (isset($url[2])) {
      $fName = $arr->FirstName;
      $lName = $arr->LastName;
      $age = $arr->Age;
      $salary = $arr->Salary;
      $team = $url[2];

      $sql = "INSERT INTO players VALUES ('$fName', '$lName', $age, $salary, '$team')";
      $conn->query($sql);

      if(!$conn->error){
        echo "true";
      }
      else{
        echo "false";
      }
    }
    else{
      $sql = "INSERT INTO teams VALUES ('$arr->Name', '$arr->City')";
      $conn->query($sql);
      //the following 4 variables were needed to make the query work. For some reason PHP wouldn't convert a string to a string (because that makes sense)
      $cap = $arr->stadium->Capacity;
      $price = (float)($arr->stadium->TicketPrice);
      $stdName = $arr->stadium->Name;
      $team = $arr->Name;
      $sql = "INSERT INTO stadiums VALUES ('$stdName', $cap, $price, '$team')";
      $conn->query($sql);

      //simple error codes for the tester if the query fails
      if(!$conn->error){
        echo "true";
      }
      else{
        echo "false";
      }
    }

    return;
  }

  function put($conn){
    $request = file_get_contents('php://input'); //reading in the passed json then decoding it on the next line
    $arr = json_decode($request);

    $team = $arr->Name; //grabbing the team name to clean up the SQL

    $sql = "UPDATE teams SET t_city='$arr->City' WHERE t_name='$team'";
    $conn->query($sql);

    //converting the object into strings and ints
    $stadium = $arr->stadium->Name;
    $capacity = $arr->stadium->Capacity;
    $price = $arr->stadium->TicketPrice;

    $sql = "UPDATE stadiums SET s_name='$stadium', capacity=$capacity, price=$price WHERE FKt_name='$team'";
    $conn->query($sql);
    return;
  }

  function del($conn){ //runs DELETE queries on the database in order to clear all info
    $sql = "DELETE FROM players";
    $result = $conn->query($sql);
    $sql = "DELETE FROM stadiums";
    $result = $conn->query($sql);
    $sql = "DELETE FROM teams"; //We must delete the teams last because of constraints in the database
    $result = $conn->query($sql);

    if(!result){
      echo "false";
    }
    else{
      echo "true";
    }
    return;
  }

  function get($url, $conn){
    $sql = "SELECT t_name AS Name, t_city AS City FROM teams"; //making the initial select statement to get the information about all teams
    if (isset($url[2])) { //if requesting a specific team, append a WHERE clause to the SQL to only get that team
      $sql = $sql . " WHERE t_name='$url[2]'";
    }
    $result = $conn->query($sql);

    $final = array(); //creating the array to be used for converting to JSON
    if($result->num_rows > 0){
      while($row = $result->fetch_assoc()){
        $row_array = array(); //creating an array to be used as the team object
        $row_array['City'] = $row['City']; //adding the team city to the team object
        $row_array['HomeStadium'] = array(); //making a sub array of team which will hold a stadium object
        $row_array['Name'] = $row['Name']; //adding the team name to the team object
        $row_array['Players'] = array(); //making a sub array of team which will hold player objects
        $name = $row['Name']; //grabbing the team name to use in the other select statements

        //begin grabbing stadium objects
        $stadium_query = "SELECT s_name AS StadiumName, capacity AS Capacity, price AS Price FROM stadiums WHERE FKt_name='$name'";
        $stadium_result = $conn->query($stadium_query);
        while ($stadium_rows = $stadium_result->fetch_assoc()) {
          $row_array['HomeStadium'] = (object)array( //using a 2D array to set up the stadium objects attributes, then converting to an object as per the API specs
            'Capacity' => (int)$stadium_rows['Capacity'],
            'Name' => $stadium_rows['StadiumName'],
            'TicketPrice' => floatval($stadium_rows['Price'])
          );
        }

        //begin grabbing player objects
        $player_query = "SELECT first_name AS FirstName, last_name AS LastName, age AS Age, salary AS Salary FROM players WHERE FKt_name='$name'";
        $player_result = $conn->query($player_query);
        while($player_rows = $player_result->fetch_assoc()){ //this while loop adds the players to an array of Players
          $row_array['Players'][] = array(
            'FirstName' => $player_rows['FirstName'],
            'LastName' => $player_rows['LastName'],
            'Age' => (int)$player_rows['Age'],
            'Salary' => (int)$player_rows['Salary']
          );
        }
        array_push($final, $row_array); //adding each team object to the final array
      }
    }
    elseif(isset($url[2])){ //if the team requested does not exist show client NULL
      echo NULL;
      return;
    }

    if(isset($url[3]) && $url[3] == 'Players'){ //only encoding the players of a specified team
      header('Content-type: application/json; charset=utf-8');
      echo json_encode($final[0]['Players']);
      return;
    }

    if(isset($url[3]) && $url[3] == 'Stadium'){ //only encoding the stadium of a specified team
      header('Content-type: application/json; charset=utf-8');
      echo json_encode($final[0]['HomeStadium']);
      return;
    }
    if(isset($url[2])){
      header('Content-type: application/json; charset=utf-8');
      echo json_encode($final[0]); //used if the request is for a single team
    }

    header('Content-type: application/json; charset=utf-8');
    echo json_encode($final); //used if the request is for all the teams
  }

  $conn->close(); //closing out the MySQL connection
?>
