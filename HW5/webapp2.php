<?php ini_set('memory_limit', '-1'); ?>
<?php

// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');

$limit = 10;
$query = isset($_REQUEST['search']) ? $_REQUEST['search'] : false;

$results = false;

$f_to_u = array();
$lines = file("URLtoHTML_mercury.csv");	

foreach ($lines as $line) {
	$key_and_val = explode(",", $line);
	$f_to_u[$key_and_val[0]] = $key_and_val[1];
}

if ($query){
	
	// The Apache Solr Client library should be on the include path
	// which is usually most easily accomplished by placing in the
	// same directory as this script ( . or current directory is a default
	// php include path entry in the php.ini)
	require_once('./solr-php-client/Apache/Solr/Service.php');

	// create a new solr service instance - host, port, and corename
	// path (all defaults in this example)
	$solr = new Apache_Solr_Service('localhost', 8983, '/solr/hw4/');

	// if magic quotes is enabled then stripslashes will be needed
	if (get_magic_quotes_gpc() == 1){
		$query = stripslashes($query);
	}

	// in production code you'll always want to use a try /catch for any
	// possible exceptions emitted by searching (i.e. connection
	// problems or a query parsing error)
	try{	
		$results = $solr->search($query, 0, $limit);

		$corrected="";

		if((int) $results->response->numFound == 0){
			include 'SpellCorrector.php';

			$splitted =  explode(" ", $query);
			
			foreach($splitted as $word){
				$corrected .= SpellCorrector::correct($word);
				$corrected .= " ";
			}

			$results = $solr->search($corrected, 0, $limit);
		}

	}
	catch (Exception $e){
		// in production you'd probably log or email this error to an admin
		// and then show a special message to the user but for this example
		// we're going to show the full exception
		die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
	}
}

// Display results
if ($results){
	$total = (int) $results->response->numFound;
	$start = min(1, $total);
	$end = min($limit, $total);

	$output = "";
	
	if($corrected != ""){
		$output .= "<div> Showing results for: ".$corrected."</div>";
		$output .= "<div> Instead of: ".$query."</div>";
		$output .= "<br>";
	}

	$output .= "<div> Results ".$start." - ".$end." of ".$total.":</div>";
	$output .= "<ol>";


	// Iterate through documents
	foreach ($results->response->docs as $doc){
		$title = $doc->title;
		$url = $doc->og_url;
		$id = $doc->id;
		$desc = $doc->og_description;

		if($desc == "" || $desc == null){
			$desc = "NA";
		}
		if($url == "" || $url == null){
			$url = $f_to_u[end(explode("/",$id))];
		}

		$output .=  "<li>";
		$output .=  "Title: <a href=".$url." target='_blank'>".$title."</a></br>";
		$output .= 	"URL: <a href=".$url." target='_blank'>".$url."</a></br>";
		$output .= 	"Description: ".$desc."</br>";
		$output .= 	"ID: ".$id."</br>";

		// For snippets!
		$html = file_get_contents($url);
		$dom = new DOMDocument;
		$dom->loadHTML($html);
		$paragraphs = $dom->getElementsByTagName('p');
		
		$snip = "Not in paragraphs or title?";

		foreach ($paragraphs as $p) {

			$sentences = explode('.', $p->nodeValue);

			foreach($sentences as $s){

				$which = trim(strtolower($query));

				if($corrected != ""){
					$which = trim(strtolower($corrected));
				}

    			$matching = strpos(strtolower($s), $which);
    			
    			if($matching){
    				$snip = $s; 
    				$snip .= ".";
    				break;
    			}
			}
		}


		$output .= "Snippet: ".$snip."</br>";
		$output .=  "</li>";
	}


	$output .= "</ol>";
}



?>

<html>
	<head>
	<meta charset = "utf-8">
	<title>PHP Solr Client CSCI572 HW4</title>
	<link href = "https://code.jquery.com/ui/1.10.4/themes/ui-lightness/jquery-ui.css" rel = "stylesheet">
	<script src = "https://code.jquery.com/jquery-1.10.2.js"></script>
	<script src = "https://code.jquery.com/ui/1.10.4/jquery-ui.js"></script>
	<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>

	<script>
		$(function(){
            $( "#search" ).autocomplete({
               source: "auto.php"
            });
		});
	</script>
	</head>
	<body>
		<div class = "ui-widget">
		<form accept-charset="utf-8" method="get">
			<label for="search">Search:</label>
			<input type="text" id="search" name="search" placeholder="Placeholder query" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/>
			<input type="submit" value="GO"/>
		</form>
		</div>
		<?php print("$output");?>
	</body>
</html>