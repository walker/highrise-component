<?php

vendor('phpHighrise');

class HighriseComponent extends Object {
	var $http;
	
	var $highrise;

	// Set up your API key and secret.
	// Get these from your developer page at
	// http://developer.37signals.com/highrise/
	var $auth_key;
	var $api_url;
	
	var $url_end = "";
	var $meth = "";
	var $parser = "";

	//Setup the basics
	function startup(&$controller) 
	{
		error_reporting(E_ALL);
		
		//not sure why I originally moved this here, put it back into the request() function
		require_once('vendors/HTTP/Request.php');
		// 		$req = new Http_Request();
		
		$this->auth_key = Configure::read('Highrise.authkey');
		$this->api_url = "http://" . Configure::read('Highrise.url') . ".highrisehq.com/";
		$this->uname = Configure::read('Highrise.username');
		$this->password = Configure::read('Highrise.password');

		$this->highrise = new phpHighrise($this->api_url);
	}

//just so there aren't a million "pr"'s in the following call functions
//it happens here, comment out to do something different
function showResults($results)
{
	return $results;
}

//all the list and show type functions take one parameter, each being different
//so those can all be taken care of here
function callFunction($function, $parm)
{
	$xml = $this->{$function}($parm);
	$results = $this->highrise->request($xml);
	$this->showResults($results);
}

//call a get function
//getCompany and getNote only take 1 parameter, so parm2 is optional
function callGet($function, $parm1, $parm2 = null)
{
	if ( $parm2 != null )
		$xml = $this->{$function}($parm1, $parm2);
	else
		$xml = $this->{$function}($parm1);
	
	$results = $this->highrise->request($xml);
	$this->showResults($results);
}

//create takes an array of parameters, which could be done in the callFunction() area
//but I think it makes sense if it has it's own section
function callCreate($function, $parms, $contact = null)
{
	if ( $contact != null )
		$xml = $this->{$function}($parms, $contact);
	else
		$xml = $this->{$function}($parms);
	$request = $this->highrise->request($xml);
	$this->showResults($results);
}

//updates all take 2 parameters, the second one being an array, so they are called from here
function callUpdate($function, $id, $parms, $contact = null)
{
	if ( $contact != null )
		$xml = $this->{$function}($id, $parms, $contact);
	else
		$xml = $this->{$function}($id, $parms);
		
	$results = $this->highrise->request($xml);
	$this->showResults($results);
}


/*******************************
**    PERSON
*******************************/

	//finds a person and displays their info
	//needs the request object as well because we need to do our request and then filter out the results
	function getPerson($name, $email)//, $req)
	{
		//default to false
		$found_person = false;
		
		//search for people with a matching name
		$xml = $this->listPeopleBySearchTerm($name);
		
		//perform the search request
		$data = $this->highrise->request($xml);
		
		//take the search data and convert it to an array 
		$data = $this->highrise->convertXmlToArray($data, "person");
		//pr($data);

		//at least 1 result was returned
		if (count($data) > 0)
		{
			//filter those people out based on the specified email address
			foreach($data as $id => $val)
			{
				if (trim($val['email-addresses-address']) == trim($email))
				{
					$found_person = true;
					$person = $val;
					break;
				}
			}
		}
		//no results were returned from the search
		else
		{
			$found_person = false;
		}
		
		//person was found, output link to the person's highrise page
		if ($found_person)
		{
			echo "Someone was found!";
			echo "<a href='".$this->api_url."people/".$person['person-id']."' target='external'>".$person['person-first-name']." ".$person['person-last-name']."</a>";
			pr($person);
		}
		//nobody was found, maybe put a link to adding the new person?
		else
		{
			echo "Nobody was found, do you want to make a new person?";
			echo "<a href=''>Link to add a person</a>";
		}
	}
	
	//gets a persons info
	function showPerson($id)
	{
		$this->highrise->setURL("people/" . $id . ".xml");
		$this->highrise->setMethod();
	}
	
	//displays a list of all people
	//pass in offset to use for paging
	function listAllPeople($offset = 0)
	{
		$this->highrise->setURL("people.xml?n=" . $offset);
		$this->highrise->setMethod();
		//$this->highrise->request($xml);
	}
	
	//displays a list of all people with a particular title
	function listPeopelWithTitle($title)
	{
		$this->highrise->setURL("people.xml?title=" . $title);
		$this->highrise->setMethod();
	}
	
	//displays a list of all people with a particular tag
	function listPeopleWithTag($tag_id)
	{
		$this->highrise->setURL("people.xml?tag_id=" . $tag_id);
		$this->highrise->setMethod();
	}
	
	//displays a list of people with a certain company 
	function listPeopleWithCompany($company_id)
	{
		$this->highrise->setURL("companies/" . $company_id . "/people.xml");
		$this->highrise->setMethod();
	}
	
	//search names for a person
	function listPeopleBySearchTerm($term)
	{
		$this->highrise->setURL("/people/search.xml?term=$term");
		$this->highrise->setMethod();
	}
	
	//list people who have been created or modified since a time
	//$time requires the format of yyyymmddhhmmss
	function listPersonSinceTime($time)
	{
		$this->highrise->setURL("people.xml?since=" . $time);
		$this->highrise->setMethod();
	}
	
	//creates a note for a person
	function createNoteForPerson($person_id, $properties)
	{
		$this->highrise->setURL("people/" . $person_id . "/notes.xml");
		$this->highrise->setMethod("POST");
		
		$tag = "<note>";
		$tag .= $this->highrise->buildTag($properties);
		$tag .= "</note>";
		
		return $tag;
	}
	
	//create a person
	//use getPerson() and this will be called if no person is found
	function createPerson($properties, $contactdata = null)
	{
		//all tags need to start with <person>
		$tag = '<person>';
		
		//build all other tags from properties passed in
		$tag .= $this->highrise->buildTag($properties);
		$tag .= "<contact-data>";

		//if contact-data was provided, tack it on here
		if ( $contactdata != null )
		{
			foreach($contactdata as $key => $val)
			{
				$tag .= $this->highrise->buildContactDataTag($key, $val);
			}
		}

		$tag .= "</contact-data>";
		//end person tag
		$tag .= '</person>';
	
		//set the url to people.xml
		$this->highrise->setURL("people.xml");
		$this->highrise->setMethod("post");
	
		//return the tag
		return $tag;
	}
	
	//update a person
	//user getPerson() and this will be called if new info is sent
	function updatePerson($person_id, $properties, $contactdata = null)
	{
		//uses post data to update the specified person
		$this->highrise->setURL("people/" . $person_id . ".xml");
		$this->highrise->setMethod("put");
		
		$tag = "<person>";
		$tag .= $this->highrise->buildTag($properties);
		$tag .= "<contact-data>";
		
		//if contact-data was provided, tack it on
		if ( $contactdata != null )
		{
			foreach($contactdata as $key => $val)
			{
				$tag .= $this->highrise->buildContactDataTag($key, $val);
			}
		}
		
		$tag .= "</contact-data>";
		$tag .= "</person>";

		return $tag;
	}
	
	/*****NOT BEING USED
	function destroyPerson($person_id)
	{
		return "people/" . $person_id . ".xml";
	}
	*****/


/*******************************
**    COMPANIES
*******************************/
	
	//finds a company by it's name
	function getCompany($company_name)//, $req)
	{
		//search for people with a matching name
		$xml = $this->listCompaniesBySearchTerm($company_name);
		
		//perform the search request
		$data = $this->highrise->request($xml);

		//take the search data and convert it to an array 
		$data = $this->highrise->convertXmlToArray($data, "company");
		//pr($data);

		//at least 1 result was returned
		if (count($data) > 0)
		{
			//go through all found companies and echo link with name
			foreach($data as $company => $val)
			{
				echo "Results were found!<br>";
				echo "<a href='".$this->api_url."companies/".$val['company-id']."'  target='external'>".$val['company-name']."</a><br>";
			}
			
			//pr($data);
		}
		//no companies were found
		else
		{
			echo "No companies found, would you like to add one?<br>";
			echo "<a href=''>Link to Add New Company</a>";
		}
	}
	
	//shows a companies info
	function showCompany($company_id)
	{
		$this->highrise->setURL("companies/" . $company_id . ".xml");
		$this->highrise->setMethod();
	}
	
	//lists all companies
	//pass in offset to use for paging
	function listAllCompanies($offset = 0)
	{
		$this->highrise->setURL("companies.xml?n=" . $offset);
		$this->highrise->setMethod();
	}
	
	//list companies that have a certain tag applied to them
	function listCompaniesWithTag($tag_id)
	{
		$this->highrise->setURL("companies.xml?tag_id=" . $tag_id);
		$this->highrise->setMethod();
	}
	
	//search company names for a specific one
	function listCompaniesBySearchTerm($term)
	{
		$this->highrise->setURL("companies/search.xml?term=" . $term);
		$this->highrise->setMethod();
	}
	
	//lists companies that have been modified or created since a certain time
	//time must be in the format of yyyymmddhhmmss
	function listCompaniesSinceTime($time)
	{
		$this->highrise->setURL("companies.xml?since=" . $time);
		$this->highrise->setMethod();
	}
	
	//creates a new company
	//should be called indirectly through getCompany()
	function createCompany($properties)
	{
		//uses POST'ed values to create a new company 
		$this->highrise->setURL("companies.xml");
		$this->highrise->setMethod("post");
		
		$tag = "<company>";
		$tag = $this->highrise->buildTag($properties);
		$tag .= "<contact-data>";

		//if contact-data was provided, tack it on here
		if ( $contactdata != null )
		{
			foreach($contactdata as $key => $val)
			{
				$tag .= $this->highrise->buildContactDataTag($key, $val);
			}
		}

		$tag .= "</contact-data>";
		$tag .= "</company>";
		
		return $tag;
	}
	
	//updates an existing company
	//should be called indirectly through getCompany()
	function updateCompany($company_id, $properties)
	{
		//uses posted values to update a companies info
		$this->highrise->setURL("companies/" . $company_id . ".xml");
		$this->highrise->setMethod("put");
		
		$tag = "<company>";
		$tag .= $this->highrise->buildTag($properties);
		$tag .= "<contact-data>";

		//if contact-data was provided, tack it on here
		if ( $contactdata != null )
		{
			foreach($contactdata as $key => $val)
			{
				$tag .= $this->highrise->buildContactDataTag($key, $val);
			}
		}

		$tag .= "</contact-data>";
		$tag .= "</company>";
		
		return $tag;
	}
	
	/****** NOT USING
	//deletes an existing company
	function destroyCompany($company_id)
	{
		return "companies/" . $company_id . ".xml";
	}
	*******/
	
/*******************************
**    CASES
*******************************/
	
	//looks for specified case and displays case info
	//searches open cases by default, not closed
	function getCase($case_name, $open = true)
	{
		$found_match = false;
		$kase = array();
		
		//search for people with a matching name
		$xml = $this->listAllCases($open);
		
		//perform the search request
		$data = $this->highrise->request($xml);

		//take the search data and convert it to an array 
		$data = $this->highrise->convertXmlToArray($data, "kase");
		//pr($data);

		//at least 1 result was returned
		if (count($data) > 0)
		{
			//go through all found companies and echo link with name
			foreach($data as $kase => $val)
			{
				if ( trim(strtolower($val['kase-name'])) == trim(strtolower($case_name)) )
				{
					$found_match = true;
					$kase = $val;
				}
				
				//pr($val);
			}
			
			//pr($data);
		}
		//no companies were found
		else
		{
			$found_match = false; 
		}
		
		//found an exact match
		if ( $found_match )
		{
			echo "Results were found!<br>";
			echo "<a href='".$this->api_url."kases/".$val['kase-id']."'  target='external'>".$val['kase-name']."</a><br>";
			pr($kase);
		}
		else
		{
			echo "No cases found, would you like to add one?<br>";
			echo "<a href=''>Link to Add New Case</a>";
		}
	}
	
	//show a particular case's info
	function showCase($case_id)
	{
		$this->highrise->setURL("kases/" . $case_id . ".xml");
		$this->highrise->setMethod();
	}
	
	//list all cases that are currently open 
	//pass in false to get closed cases
	function listAllCases($open = true)
	{
		if ( $open )
		{
			$this->highrise->setURL("kases/open.xml");
		}
		else
		{
			$this->highrise->setURL("kases/closed.xml");
		}
		
		$this->highrise->setMethod();
	}
	
	//create a new case
	//will be called if getCase() can't find a particular case
	function createCase($properties)
	{
		//uses POST'ed data to create a new case
		$this->highrise->setURL("kases.xml");
		$this->highrise->setMethod("post");
		
		$tag = "<kase>";
		$tag .= $this->highrise->buildTag($properties);
		$tag .= "</kase>";
		
		return $tag;
	}
	
	//update an existing case
	//will be called indirectly through getCase()
	function updateCase($case_id, $properties)
	{
		//uses POST'ed data to update an existing case
		$this->highrise->setURL("kases/" . $case_id . ".xml");
		$this->highrise->setMethod("put");
		
		$tag = "<kase>";
		$tag .= $this->highrise->buildTag($properties);
		$tag .= "</kase>";
		
		return $tag;
	}
	
	/***** NOT BEING USED
	//deletes an existing case
	function destroyCase($case_id)
	{
		return "kases/" . $case_id . ".xml";
	}
	*****/
	
/*******************************
**    NOTES
*******************************/
	
	//shows the info for a particular note
	function showNote($note_id)
	{
		$this->highrise->setURL("notes/" . $note_id . ".xml");
		$this->highrise->setMethod();
	}
	
	//lists notes that are related to the specified person
	function listALlNotesFromPerson($person_id)
	{
		$this->highrise->setURL("people/" . $person_id . "/notes.xml");
		$this->highrise->setMethod();
	}
	
	//lists notes that are related to the specified case
	function listAllNotesFromCase($case_id)
	{
		$this->highrise->setURL("kases/" . $case_id . "/notes.xml");
		$this->highrise->setMethod();
	}
	
	//creates a new note
	function createNote($properties, $person_id = null)
	{
		//uses the POST'ed data to create a new note
		if ( $person_id != null )
		{
			$this->highrise->setURL("people/".$person_id."/notes.xml");
		}
		else
		{
			$this->highrise->setURL("notes.xml");
		}
		
		$this->highrise->setMethod("post");
		
		$tag = "<note>";
		$tag .= $this->highrise->buildTag($properties);
		$tag .= "</note>";
		
		return $tag;
	}
	
	//updates an existing note
	function updateNote($note_id, $properties)
	{
		//uses POST'ed data to update an existing note
		$this->highrise->setURL("notes/" . $note_id . ".xml");
		$this->highrise->setMethod("put");
		
		$tag = "<note>";
		$tag .= $this->highrise->buildTag($properties);
		$tag .= "</note>";
		
		return $tag; 
	}
	
	/***** NOT BEING USED
	//delete an existing note
	function destroyNote($note_id)
	{
		return "notes/" . $note_id . ".xml";
	}
	*****/
}

?>   

