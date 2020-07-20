<?php

class Toast_Norole_Controller extends WP_REST_Controller {
  public function register_routes() {
    $namespace = 'rsvptm/v1';
    $path = 'norole/(?P<post_id>[0-9]+)';

    register_rest_route( $namespace, '/' . $path, [
      array(
        'methods'             => 'GET',
        'callback'            => array( $this, 'get_items' ),
        'permission_callback' => array( $this, 'get_items_permissions_check' )
            ),
        ]);     
    }

  public function get_items_permissions_check($request) {
    return true;
  }

public function get_items($request) {
    global $wpdb;
    $hasrole = array();
    $norole = array();
    $post_id = $request['post_id'];
    $date = get_rsvp_date($post_id);
    $absences = get_absences_array($post_id);
    $sql = "SELECT * FROM `$wpdb->postmeta` where post_id=".$post_id." AND meta_value REGEXP '^[0-9]+$' AND BINARY meta_key RLIKE '^_[A-Z].+[0-9]$' ";
    $results = $wpdb->get_results($sql);
    foreach ($results as $row)
        $hasrole[] = $row->meta_value;
    $users = get_users('blog_id='.get_current_blog_id());
    foreach($users as $user)
        {
            if(!in_array($user->ID,$hasrole) && !in_array($user->ID,$absences))
                {
                $userdata = get_userdata($user->ID);
                $norole[] = $userdata->first_name .' '. $userdata->last_name;
                }
        }
    sort($norole);
    return new WP_REST_Response($norole, 200);
  }
}

class WPTContest_Order_Controller extends WP_REST_Controller {
	public function register_routes() {
	  $namespace = 'wptcontest/v1';
	  $path = 'order/(?P<post_id>[0-9]+)';///(?P<nonce>.+)
  
	  register_rest_route( $namespace, '/' . $path, [
		array(
		  'methods'             => 'GET',
		  'callback'            => array( $this, 'get_items' ),
		  'permission_callback' => array( $this, 'get_items_permissions_check' )
			  ),
		  ]);     
	  }
  
	public function get_items_permissions_check($request) {
	  return true;
	}
  
  public function get_items($request) {
	  global $wpdb;
	  $order = get_post_meta($request['post_id'],'tm_scoring_order',true);
	  return new WP_REST_Response($order, 200);
	}
}

class WPTContest_VoteCheck extends WP_REST_Controller {
	public function register_routes() {
	  $namespace = 'wptcontest/v1';
	  $path = 'votecheck/(?P<post_id>[0-9]+)';///(?P<nonce>.+)
  
	  register_rest_route( $namespace, '/' . $path, [
		array(
		  'methods'             => 'GET',
		  'callback'            => array( $this, 'get_items' ),
		  'permission_callback' => array( $this, 'get_items_permissions_check' )
			  ),
		  ]);     
	  }
  
	public function get_items_permissions_check($request) {
	  return true;
	}
  
  public function get_items($request) {
	  global $wpdb;
	  $votes = toast_scores_check($request['post_id']);
	  return new WP_REST_Response($votes, 200);
	}
}


class WPTContest_GotVote extends WP_REST_Controller {
	public function register_routes() {
	  $namespace = 'wptcontest/v1';
	  $path = 'votereceived/(?P<post_id>[0-9]+)/(?P<judge_id>[0-9]+)';///(?P<nonce>.+)
  
	  register_rest_route( $namespace, '/' . $path, [
		array(
		  'methods'             => 'GET',
		  'callback'            => array( $this, 'get_items' ),
		  'permission_callback' => array( $this, 'get_items_permissions_check' )
			  ),
		  ]);     
	  }
  
	public function get_items_permissions_check($request) {
	  return true;
	}
  
  public function get_items($request) {
    global $wpdb;
    $confirmed = (boolean) get_post_meta($request['post_id'],'tm_vote_received'.$request['judge_id'],true);
	  return new WP_REST_Response($confirmed, 200);
	}
}
//check for update_post_meta($post_id,'tm_vote_received'.$index,true);

class WPT_Timer_Control extends WP_REST_Controller {
	public function register_routes() {
	  $namespace = 'toasttimer/v1';
	  $path = 'control/(?P<post_id>[0-9]+)';///(?P<nonce>.+)
  
	  register_rest_route( $namespace, '/' . $path, [
		array(
		  'methods'             => array('GET','POST'),
		  'callback'            => array( $this, 'get_items' ),
		  'permission_callback' => array( $this, 'get_items_permissions_check' )
			  ),
		  ]);     
	  }
  
	public function get_items_permissions_check($request) {
	  return true;
	}
  
  public function get_items($request) {
	if(!empty($_POST))
		{
			$control = $_POST;
			update_post_meta($request['post_id'],'timing_light_control',$control);
		}
	//else
		$control = get_post_meta($request['post_id'],'timing_light_control',true);
	rsvpmaker_debug_log($request,'timing light API request');
	rsvpmaker_debug_log($_REQUEST,'timing light server request');
	rsvpmaker_debug_log($control,'timing light control');
	  return new WP_REST_Response($control, 200);
	}
}

class Toast_Agenda_Timing extends WP_REST_Controller {
	public function register_routes() {
	  $namespace = 'rsvptm/v1';
	  $path = 'agendatime/(?P<post_id>[0-9]+)';
  
	  register_rest_route( $namespace, '/' . $path, [
		array(
		  'methods'             => 'GET',
		  'callback'            => array( $this, 'get_items' ),
		  'permission_callback' => array( $this, 'get_items_permissions_check' )
			  ),
		  ]);     
	  }
  
	public function get_items_permissions_check($request) {
	  return true;
	}
  
  public function get_items($request) {
	$timing = get_agenda_timing($request['post_id']);
	  return new WP_REST_Response($timing, 200);
	}
  }

class Toast_Manual_Lookup extends WP_REST_Controller {
	public function register_routes() {
	  $namespace = 'rsvptm/v1';
	  $path = 'type_to_manual/(?P<type>.+)';
  
	  register_rest_route( $namespace, '/' . $path, [
		array(
		  'methods'             => 'GET',
		  'callback'            => array( $this, 'get_items' ),
		  'permission_callback' => array( $this, 'get_items_permissions_check' )
			  ),
		  ]);     
	  }
  
	public function get_items_permissions_check($request) {
	  return true;
	}
  
  public function get_items($request) {
	$type = urldecode($request['type']);
	$options = get_manuals_by_type_options($type);
	$projects = '';
	$pa = get_projects_array('options');
	if($type == 'Other')
		$manual = 'Other Manual or Non Manual Speech';
	elseif($type == 'Manual')
		$manual = "COMPETENT COMMUNICATION";
	elseif($type == 'Pathways 360')
		$manual = "Pathways 360 Level 5 Demonstrating Expertise";
	else
		$manual = $type .' Level 1 Mastering Fundamentals';
	if($manual == 'Pathways 360')
		$projects = $pa[$manual];
	else
		$projects = '<option value="">Select Project</option>'.$pa[$manual];
	  return new WP_REST_Response(array('list' => $options, 'projects' => $projects), 200);
	}
}

class Editor_Assign extends WP_REST_Controller {
	public function register_routes() {
	  $namespace = 'rsvptm/v1';
	  $path = 'editor_assign';
  
	  register_rest_route( $namespace, '/' . $path, [
		array(
		  'methods'             => 'POST',
		  'callback'            => array( $this, 'handle' ),
		  'permission_callback' => array( $this, 'get_items_permissions_check' )
		),
		  ]);     
	  }
  
	public function get_items_permissions_check($request) {
	  return (is_user_logged_in() && current_user_can('edit_signups'));
	}
  
  public function handle($request) {
	global $wpdb, $current_user;
	$post_id = (int) $_POST["post_id"];
	$user_id = $_POST["user_id"];
	$role = $_POST["role"];
	$editor_id = (empty($_POST["editor_id"])) ? $current_user->ID : (int) $_POST["editor_id"];
	$timestamp = get_rsvp_date($post_id);
	$was = get_post_meta($post_id,$role,true);
	update_post_meta($post_id,$role,$user_id);
	if(strpos($role,'Speaker'))
		{
		delete_post_meta($post_id,'_manual'.$role);
		delete_post_meta($post_id,'_project'.$role);
		delete_post_meta($post_id,'_title'.$role);
		delete_post_meta($post_id,'_intro'.$role);
		}
	if(time() > strtotime($timestamp))
		{
		$key = make_tm_usermeta_key ($role, $timestamp, $post_id);
		$roledata = make_tm_roledata_array ('wp_ajax_editor_assign');
		if($user_id)
			update_user_meta($user_id,$key,$roledata);
		$sql = $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE meta_key=%s AND user_id != %d",$key,$user_id);
		$wpdb->query($sql);
		}
	$name = get_member_name($user_id);
	$status = sprintf('%s assigned to %s',preg_replace('/[\_0-9]/',' ',$role),$name);
	$log = get_member_name($editor_id) .' assigned '.clean_role($role).' to '.get_member_name($user_id).' for '.date('F jS, Y',strtotime($timestamp));
	if($was)
		$log .= ' (was: '.get_member_name($was).')';
	$log .= ' <small><em>(Posted: '.date('m/d/y H:i').')</em></small>';
	
	add_post_meta($post_id,'_activity_editor', $log );
	$type = '';
	$manual = '';
	$projects = '';
	$options = '';
	if(strpos($role,'peaker')) {
		$track = get_speaking_track($user_id);
		$type = $track["type"];
		$manual = $track["manual"];
		if(!empty($manual) && !strpos($manual,'Manual') )
			update_post_meta($post_id,'_manual'.$role,$manual);
		$options = sprintf('<option value="%s">%s</option>',$track['manual'],$track['manual']);
		$options .= get_manuals_by_type_options($type);
		$projects = '<option value="">Select Project</option>'.$track["projects"];
	}
	  return new WP_REST_Response(array('status' => $status, 'type' => $type, 'list' => $options, 'projects' => $projects), 200);
	}
}

class WPTM_Reports extends WP_REST_Controller {
	public function register_routes() {
	  $namespace = 'rsvptm/v1';
	  $path = 'reports/(?P<report>.+)/(?P<user_id>[0-9]+)';
  
	  register_rest_route( $namespace, '/' . $path, [
		array(
		  'methods'             => 'GET',
		  'callback'            => array( $this, 'handle' ),
		  'permission_callback' => array( $this, 'get_items_permissions_check' )
		),
		  ]);     
	  }
  
	public function get_items_permissions_check($request) {
	  return (is_user_logged_in() && current_user_can('view_reports'));
	}
  
  public function handle($request) {
	global $wpdb;
	$report = $request["report"];
	$user_id = $request["user_id"];
	$content = '';
	if($report == 'speeches_by_manual')
		$content = speeches_by_manual($user_id);
	elseif($report == 'traditional_program')
		$content = toastmasters_progress_report($user_id);
	elseif($report == 'traditional_advanced') {
		ob_start();
		if($user_id)
			{
			$userdata = get_userdata($user_id);
			toastmasters_advanced_user ($userdata,true);	
			}
		else
			{
			echo 'Select member from the list above';
			echo toastmasters_advanced();
			}		
		$content = ob_get_clean();
	}
	elseif($report == 'pathways') {
		ob_start();
		pathways_report($user_id);
		$content = ob_get_clean();
	}
	  return new WP_REST_Response(array('report' => $report, 'content' => $content), 200);
	}
}

add_action('rest_api_init', function () {
     $toastnorole = new Toast_Norole_Controller();
     $toastnorole->register_routes();
     $order_controller = new WPTContest_Order_Controller();
     $order_controller->register_routes();
     $votecheck_controller = new WPTContest_VoteCheck();
     $votecheck_controller->register_routes();
     $gotvote_controller = new WPTContest_GotVote();
     $gotvote_controller->register_routes();
     $timer_controller = new WPT_Timer_Control();
	 $timer_controller->register_routes();
	 $manual = new Toast_Manual_Lookup();
	 $manual->register_routes();
	 $assign = new Editor_Assign();
	 $assign->register_routes();
	 $rsvpexp = new RSVP_Export();
	 $rsvpexp->register_routes();
	 $repo = new WPTM_Reports();
	 $repo->register_routes();
   } );
?>