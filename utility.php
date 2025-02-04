<?php

function wp4t_haverole($post_id) {
	global $wpdb;
	$haverole[999999] = 'placeholder';
	$sql = "SELECT * FROM $wpdb->postmeta WHERE post_id=$post_id AND BINARY meta_key RLIKE '^_[A-Z].+[0-9]$' AND meta_value > 0";
	$results = $wpdb->get_results($sql);
	if($results)
	foreach($results as $row) {
		if(is_numeric($row->meta_value))
		$haverole[$row->meta_value] = clean_role($row->meta_key); 
	}
	return $haverole;
}

function wp4t_last_held_role($user_id, $role) {
	global $wpdb, $rsvp_options;
	$history_table = $wpdb->base_prefix.'tm_history';
	$sql = "SELECT datetime FROM $history_table WHERE role='$role' AND user_id=$user_id AND datetime < NOW() ORDER BY datetime DESC";
	$d = $wpdb->get_var($sql);
	if(empty($d))
		return;
	$t = rsvpmaker_strtotime($d);
	return rsvpmaker_date($rsvp_options['long_date'],$t);
}

function awe_user_dropdown( $role, $assigned = 0, $settings = false, $openlabel = 'Open' ) {
	if ( rsvpmaker_is_template() ) {
		return 'Member dropdown will appear here';
	}
	global $wpdb, $sortmember, $fnamesort, $histories, $post, $haverole;
	if(empty($haverole) && !empty($post->ID))
		$haverole = wp4t_haverole($post->ID);
	if(!empty($post->ID))
		$absences = get_post_meta( $post->ID, 'tm_absence' );
	if(empty($absences))
		$absences = array();
	if ( ! wp_next_scheduled( 'refresh_tm_history' ) ) {
		wp_schedule_event( rsvpmaker_strtotime( 'tomorrow 02:00' ), 'daily', 'refresh_tm_history' );
	}

	$options = '<option value="0">' . $openlabel . '</option>';

	if ( ! empty( $assigned ) && ! is_numeric( $assigned ) ) {
		$options .= sprintf( '<option value="" selected="selected">%s</option>', __( 'Guest', 'rsvpmaker-for-toastmasters' ) );
	}

	$blogusers = get_users( 'blog_id=' . get_current_blog_id() );

	foreach ( $blogusers as $user ) {

		$member = get_userdata( $user->ID );

		$findex = preg_replace( '/[^a-zA-Z]/', '', $member->first_name . $member->last_name . $member->user_login );

		$fnamesort[ $findex ] = $member;

	}

	$member = new stdClass();

	$member->ID = -1;

	$member->last_name = __( 'Available', 'rsvpmaker-for-toastmasters' );

	$member->first_name = __( 'Not', 'rsvpmaker-for-toastmasters' );

	$member->display_name = __( 'Not Available', 'rsvpmaker-for-toastmasters' );

	$member->user_login = 'not_available';

	$fnamesort['AAA'] = $sortmember['AAA'] = $member;

	$member = new stdClass();

	$member->ID = -2;

	$member->last_name = __( 'Announced', 'rsvpmaker-for-toastmasters' );

	$member->first_name = __( 'To Be', 'rsvpmaker-for-toastmasters' );

	$member->display_name = __( 'To Be Announced', 'rsvpmaker-for-toastmasters' );

	$member->user_login = 'tobe';

	$fnamesort['AAB'] = $sortmember['AAB'] = $member;

	$member = new stdClass();

	$reserved_role_label = get_option( 'wpt_reserved_role_label' );

	if ( empty( $reserved_role_label ) ) {

		$reserved_role_label = 'Ask VPE';
	}

	$member->ID = -3;

	$member->last_name = __( $reserved_role_label, 'rsvpmaker-for-toastmasters' );

	$member->first_name = __( 'Reserved', 'rsvpmaker-for-toastmasters' );

	$member->display_name = __( 'Reserved', 'rsvpmaker-for-toastmasters' ) . ' ' . $reserved_role_label;

	$member->user_login = 'tobe';

	$fnamesort['AAC'] = $sortmember['AAC'] = $member;

	ksort( $fnamesort );

	foreach ( $fnamesort as $fnindex => $member ) {

		if ( $member->ID == $assigned ) {

			$s = ' selected="selected" ';

		} else {
			$s = '';
		}

		$status = '';
		if(isset($haverole[$member->ID]))
			$status = $haverole[$member->ID];
		elseif(in_array($member->ID,$absences))
			$status = __('Planned Absence','rsvpmaker-for-toastmasters');
		elseif ( $member->ID > 0 ) {
			$held = wp4t_last_held_role($member->ID, clean_role($role));
			if ( ! empty( $held ) ) {
				$status = __( 'Last did', 'rsvpmaker-for_toastmasters' ) . ': ' . $held;
			}
		}

		if ( ! empty( $status ) ) {
			$status = '(' . $status . ')';
		}

		if ( empty( $member->first_name ) ) {

			$member->first_name = $member->display_name;
		}

			$options .= sprintf( '<option %s value="%d">%s</option>', $s, $member->ID, $member->first_name . ' ' . $member->last_name.' '.$status );

		if ( ! empty( $role ) ) {
			if ( empty( $held ) ) {
				$fnindex = '0000-00-00' . $fnindex;
			} else {
				$fnindex = date( 'Y-m-d', strtotime( $held ) ) . $fnindex;
			}

			if ( $member->ID > 0 ) { // filter out Not Available
				$heldsort[ $fnindex ] = sprintf( '<option value="%d">%s %s</option>', $member->ID, $member->first_name . ' ' . $member->last_name, $status );
			}
		}
	}

	if ( ! empty( $role ) ) {

		ksort( $heldsort );

		$options .= '<option value="0">' . $openlabel . '</option>';

		$options .= '<optgroup label="Last Did Role">';

		foreach ( $heldsort as $option ) {

			$options .= $option;

		}

		$options .= '</optgroup>';

	}

	$options = apply_filters( 'awe_dropdown_options', $options );

	if ( $settings ) {

		return '<select name="' . $role . '" id="' . $role . '_select">' . $options . '</select>';

	} elseif ( isset( $_GET['recommend_roles'] ) ) {

		return '<select name="editor_suggest[' . $role . ']" id="editor_suggest' . $role . '" class="editor_suggest" >' . $options . '</select>';

	}
	/*
	 elseif ( is_edit_roles() ) {

		return "\n\n" . '<input type="checkbox" class="recommend_instead" name="recommend_instead' . $role . '" id="recommend_instead' . $role . '" class="editor_assign" post_id="' . $post->ID . '" value="_rm' . $role . '" /> ' . __( 'Recommend instead of assign', 'rsvpmaker-for-toastmasters' ) . '<br /><select name="editor_assign[' . $role . ']" id="editor_assign' . $role . '" class="editor_assign"  post_id="' . $post->ID . '">' . $options . '</select><span id="_rm' . $role . '"></span>';

	}
	*/
	else {
		return "\n\n" . '<select name="editor_assign[' . $role . ']" id="' . $post->ID . '_editor_assign' . $role . '" class="editor_assign" post_id="' . $post->ID . '" role="' . $role . '">' . $options . '</select>';
	}

}



function awe_assign_dropdown( $role, $random_assigned ) {

	return awe_user_dropdown( $role, $random_assigned, false, 'Open' );

}

function clean_role( $role ) {
	$role = str_replace('_suggest_','',$role);
	$role = preg_replace( '/[0-9]/', '', $role );
	$role = str_replace( '_', ' ', $role );
	return trim( $role );
}

function future_toastmaster_meetings( $limit = 10 ) {
	global $wpdb;
	$event_table = $wpdb->prefix.'rsvpmaker_event';
	$sql = "SELECT *, date as datetime, ID as postID from $wpdb->posts JOIN $event_table ON $wpdb->posts.ID = $event_table.event WHERE post_status='publish' AND ts_end > ".time()." AND post_content LIKE '%wp:wp4toastmasters%' ORDER BY date LIMIT 0,$limit";
	return $wpdb->get_results($sql);
}

function past_toastmaster_meetings( $limit = 10000, $buffer = 0 ) {
	global $wpdb;
	$event_table = $wpdb->prefix.'rsvpmaker_event';
	$sql = "SELECT * from $wpdb->posts JOIN $event_table ON $wpdb->posts.ID = $event_table.event WHERE post_status='publish' AND date < NOW() AND post_content LIKE '%wp:wp4toastmasters%' ORDER BY date DESC LIMIT 0,$limit";
	return $wpdb->get_results($sql);
}

function last_toastmaster_meeting() {
	global $wpdb;
	$sql = "SELECT * FROM $wpdb->posts JOIN ".$wpdb->prefix."rsvpmaker_event WHERE post_status='publish' AND date < NOW() AND post_status='publish' AND post_content LIKE LIKE '%wp:wp4toastmasters%' ORDER BY date DESC";
	return $wpdb->get_row($sql);
}

function next_toastmaster_meeting() {
	global $wpdb;
	$event_table = $wpdb->prefix.'rsvpmaker_event';
	$sql = "SELECT * from $wpdb->posts JOIN $event_table ON $wpdb->posts.ID = $event_table.event WHERE post_status='publish' AND date > NOW() AND post_content LIKE '%wp:wp4toastmasters%' ORDER BY date";
	return $wpdb->get_row($sql);
}

function get_club_members( $blog_id = 0 ) {

	if ( empty( $blog_id ) ) {

		$blog_id = get_current_blog_id();
	}

	return get_users(
		array(
			'blog_id' => $blog_id,
			'orderby' => 'display_name',
		)
	);

}



function get_club_member_emails( $blog_id = 0 ) {

	if ( empty( $blog_id ) ) {

		$blog_id = get_current_blog_id();
	}

	$members = get_users(
		array(
			'blog_id' => $blog_id,
			'orderby' => 'display_name',
		)
	);

	$emails = array();

	foreach ( $members as $member ) {

		$emails[] = strtolower( $member->user_email );

	}

	return $emails;

}

function wpt_remove_unsubscribed($source, $unsubscribed) {
	foreach ($source as $email)
		if(!in_array($email,$unsubscribed))
			$recipients[] = $email;
	return $recipients;
}

function is_officer() {

	global $current_user;

	$officer_ids = get_option( 'wp4toastmasters_officer_ids' );

	return ( is_array( $officer_ids ) && in_array( $current_user->ID, $officer_ids ) );

}

function wpt_multiple_blocks_same( $post_id, $post_after, $post_before ) {
	static $newcontent;
	if ( ! empty( $newcontent ) ) { // prevent running more than once
		return;
	}
	$content    = $post_after->post_content;
	$newcontent = '';
	$do_update  = false;
	$uids = array();

	$newcontent = '';
	if ( strpos( $content, 'wp:wp4toastmasters/role' ) ) {
		$lines = explode( "\n", $content );
		foreach ( $lines as $line ) {
			preg_match( '/{"role":[^}]+}/', $line, $match );
			if ( ! empty( $match[0] ) ) {
				$atts = json_decode( $match[0] );
				if ( empty( $atts->count ) ) {
					$atts->count = 1;
				}
				$atts->start               = ( empty( $next_start[ $atts->role ] ) || ('custom' == $atts->role) ) ? 1 : $next_start[ $atts->role ];
				$next_start[ $atts->role ] = $atts->start + $atts->count;
				$line                      = preg_replace( '/{"role":[^}]+}/', json_encode( $atts ), $line );
			} elseif ( strpos( $line, '"uid":"' ) ) {
				$pattern = '/{.+}/';
				preg_match( $pattern, $line, $match );
				if ( ! empty( $match[0] ) ) {
					$atts = (array) json_decode( $match[0] );
					if ( in_array( $atts['uid'], $uids ) ) {
							$atts['uid'] = 'note' . rand( 100, 10000 );
							$line        = preg_replace( '/{.+}/', json_encode( $atts ), $line );
					}
					$uids[] = $atts['uid'];
				}
			}
			$newcontent .= $line . "\n";
		}
		$post_array = array(
			'ID'           => $post_id,
			'post_content' => $newcontent,
		);
		wp_update_post( $post_array );
	}
}

add_action( 'post_updated', 'wpt_multiple_blocks_same', 10, 3 );

function role_count_time( $post_id, $atts ) {

		$role       = $atts['role'];
		$start      = ( empty( $atts['start'] ) ) ? 1 : $atts['start'];
		$field_base = preg_replace( '/[^a-zA-Z0-9]/', '_', $atts['role'] );
		$count      = (int) ( isset( $atts['count'] ) ) ? $atts['count'] : 1;
		$total      = $time = 0;
		$output     = '';
	for ( $i = $start; $i < ( $count + $start ); $i++ ) {

		$field = '_' . $field_base . '_' . $i;

		$assigned = get_post_meta( $post_id, $field, true );
		if ( $assigned ) {
			$total++;
			if ( $role == 'Speaker' ) {
				$slug     = '_maxtime_Speaker_' . $i;
				$metatime = get_post_meta( $post_id, $slug, true );
				$time    += ( empty( $metatime ) ) ? 7 : (int) $metatime;
			}
		}
	}

		$output .= ' <em>' . $total . ' signed up ';
	if ( $time ) {
		$output .= "($time minutes)";
	}
		$output .= '</em>';

		return $output;
}


function get_role_assignments( $post_id, $atts ) {
	global $email_context;
	$nonce = get_post_meta($post_id,'oneclicknonce',true);
	$role = $atts['role'];

	$start = ( empty( $atts['start'] ) ) ? 1 : $atts['start'];

	$field_base = preg_replace( '/[^a-zA-Z0-9]/', '_', $atts['role'] );

	$count = (int) ( isset( $atts['count'] ) ) ? $atts['count'] : 1;

	if ( $atts['role'] == 'Speaker' ) {

		pack_speakers( $count );

	} elseif ( $count > 1 ) {

		pack_roles( $count, $field_base );
	}

	for ( $i = $start; $i < ( $count + $start ); $i++ ) {

		$field = '_' . $field_base . '_' . $i;

		$assigned = get_post_meta( $post_id, $field, true );
		if(empty($assigned) && $email_context) {
			if(empty($nonce)) {
				$nonce = wp_create_nonce('oneclick');
				update_post_meta($post_id,'oneclicknonce',$nonce);
			}
			$name = add_query_arg(array('oneclick' => $nonce,'role' => $role,'e' => '*|EMAIL|*'),get_permalink());//sprintf('&oneclick=code&role=Ah Counter&e=test@example.com');
			$name = sprintf('Open - <a href="%s#oneclick">One-Click Signup</a>',$name);
		}
		else
			$name = get_member_name( $assigned );

		$assignments[ $field ] = array(
			'role'      => $atts['role'],
			'assigned'  => $assigned,
			'name'      => $name,
			'iteration' => $i,
		);

	}

	//&& empty($_GET['email_agenda'])
	if ( ! empty( $atts['backup'] )  ) {

			$field = '_' . preg_replace( '/[^a-zA-Z0-9]/', '_', 'Backup ' . $atts['role'] ) . '_1';

			$assigned = get_post_meta( $post_id, $field, true );

			$name = get_member_name( $assigned );

			$assignments[ $field ] = array(
				'role'      => __( 'Backup', 'rsvpmaker-for-toastmasters' ) . ' ' . $atts['role'],
				'assigned'  => $assigned,
				'name'      => $name,
				'iteration' => 1,
			);

	}

	return $assignments;

}



function get_member_name( $user_id, $credentials = true ) {
	$member = null;
	if ( ! empty( $user_id ) && ! is_numeric( $user_id ) ) {

		return $user_id . ' (' . __( 'guest', 'rsvpmaker-for-toastmasters' ) . ')'; // guest ?

	} elseif ( empty( $user_id ) ) {

		return 'Open';

	} elseif ( $user_id == -1 ) {

		return 'Not Available';

	} elseif ( $user_id == -2 ) {

		return 'To Be Announced';

	} elseif ( $user_id == -3 ) {

		$reserved_role_label = get_option( 'wpt_reserved_role_label' );

		if ( empty( $reserved_role_label ) ) {

			$reserved_role_label = 'Ask VPE';
		}

		return 'Reserved ' . $reserved_role_label;

	}

	if ( is_numeric( $user_id ) ) {

		$member = get_userdata( $user_id );

		if ( empty( $member ) ) {

			return __( 'Member not found', 'rsvpmaker-for-toastmasters' );
		}

		if ( empty( $member->first_name ) && empty( $member->last_name ) ) {
			if ( empty( $member->display_name ) ) {
				$name = $member->user_login;
			} else {
				$name = $member->display_name;
			}
		} else {
			$name = $member->first_name . ' ' . $member->last_name;
		}

		if ( $credentials && ! empty( $member->education_awards ) ) {

			$name .= ', ' . $member->education_awards;
		}
	} else {
		$name = $user_id . ' (' . __( 'guest', 'rsvpmaker-for-toastmasters' ) . ')';
	}

	$name = strip_tags( $name );

	return apply_filters('get_member_name',$name, $user_id, $member);

}

function is_wp4t( $content = '' ) {

	global $post;

	if ( ! empty( $post ) && empty( $content ) ) {

		$content = $post->post_content;

	}
	if ( ( strpos( $content, '[toastmaster' ) === false ) && ( strpos( $content, 'wp:wp4toastmasters/' ) === false ) ) {

		return false;

	} else {
		return true;
	}

}

function tm_admin_page_top( $headline, $sidebar = '' ) {

	/*
	$hook = tm_admin_page_top(__('Headline','rsvpmaker-for-toastmasters'));
	tm_admin_page_bottom($hook);
	*/

	$hook = '';

	if ( is_admin() ) { // if not full screen view

		$screen = get_current_screen();

		$hook = $screen->id;

	}
	$printlink = admin_url( str_replace( '/wp-admin/', '', $_SERVER['REQUEST_URI'] ) ) . '&rsvp_print=1&'.rsvpmaker_nonce('query');
	$wordlink = admin_url( str_replace( '/wp-admin/', '', $_SERVER['REQUEST_URI'] ) ) . '&rsvp_print=word&'.rsvpmaker_nonce('query');
	$print = ( isset( $_REQUEST['page'] ) && ! isset( $_REQUEST['rsvp_print'] ) ) ? '<div style="border: thin dotted #000;width: 250px; padding: 10px; float: right;"><a target="_blank" href="' . $printlink .'">'.__('Print','rsvpmaker-for-toastmasters').'</a><br><a target="_blank" href="' . $wordlink .'">'.__('Export to Word','rsvpmaker-for-toastmasters').'</a>'.$sidebar.'</div>' : '';

	if(isset($_GET['rsvp_print'])) {
		$name = get_bloginfo('name');
		printf('<p><img src="https://toastmost.org/tmbranding/agenda-rays.png" width="525" height="60" /></p><h1>%s</h1>',$name);
	}

	printf( '<div id="wrap" class="%s toastmasters">%s<h1>%s</h1>', $hook, $print, $headline );

	return $hook;

}

function tm_admin_page_bottom( $hook = '' ) {
if(isset($_GET['rsvp_print']))
	return;
	if ( is_admin() && empty( $hook ) ) {

		$screen = get_current_screen();

		$hook = $screen->id;

	}

	printf( "\n" . '<hr /><p><small>%s</small></p></div>', $hook );

}

//$fname = apply_filters('rsvp_print_to_word',$fname);
add_filter('rsvp_print_to_word','wp4t_print_to_word');
function wp4t_print_to_word($fname) {
	if(isset($_GET['report'])) {
		if('minutes' == $_GET['report']) {
			if(isset($_GET['post_id']))
				{
					$post_id = intval($_GET['post_id']);
					$date = get_rsvp_date($post_id);
				}
			else {
				$event = last_toastmaster_meeting();
				$date = $event->date;
			}
			$fname = 'minutes-'.$date;
		}
	}
	return $fname;
}

function wpt_get_member_emails() {

	$blogusers = get_users( 'blog_id=' . get_current_blog_id() );

	foreach ( $blogusers as $user ) {

		$emails[ $user->ID ] = $user->user_email;
	}

	return $emails;

}

function wp4t_unassigned_emails( $post_id = 0 ) {

	global $post;

	if ( ! $post_id ) {

		$post_id = $post->ID;
	}

	if ( empty( $post->ID ) ) {

		$post = get_post( $post_id );
	}

	$roster = '';

	$signup = get_post_custom( $post_id );

	$data = wpt_blocks_to_data( $post->post_content );

	foreach ( $data as $item ) {

		if ( ! empty( $item['role'] ) ) {

			$role = $item['role'];

			$count = ( empty( $item['count'] ) ) ? 1 : (int) $item['count'];

			for ( $i = 1; $i <= $count; $i++ ) {

				$field = '_' . str_replace( ' ', '_', $role ) . '_' . $i;

				$roles[ $field ] = $role;

			}
		}
	}

	$has_assignment = $emails = array();

	foreach ( $roles as $field => $role ) {

		   $assigned = ( isset( $signup[ $field ][0] ) ) ? $signup[ $field ][0] : '';

		if ( ! empty( $assigned ) ) {

			$has_assignment[] = (int) $assigned;
		}
	}

	$absences = get_absences_array( $post_id );

	$has_assignment = array_merge( $has_assignment, $absences );

	$users = get_users( 'blog_id=' . get_current_blog_id() );

	foreach ( $users as $user ) {

		if ( ! in_array( $user->ID, $has_assignment ) ) {

			$emails[] = $user->user_email;
		}
	}

	return $emails;

}



function wp4t_unassigned_ids( $post_id = 0 ) {

	global $post;

	if ( ! $post_id ) {

		$post_id = $post->ID;
	}

	if ( empty( $post->ID ) ) {

		$post = get_post( $post_id );
	}

	$roster = '';

	$signup = get_post_custom( $post_id );

	$data = wpt_blocks_to_data( $post->post_content );

	foreach ( $data as $item ) {

		if ( ! empty( $item['role'] ) ) {

			$role = $item['role'];

			$count = (int) $item['count'];

			for ( $i = 1; $i <= $count; $i++ ) {

				$field = '_' . str_replace( ' ', '_', $role ) . '_' . $i;

				$roles[ $field ] = $role;

			}
		}
	}

	$has_assignment = $emails = array();

	foreach ( $roles as $field => $role ) {

		   $assigned = ( isset( $signup[ $field ][0] ) ) ? $signup[ $field ][0] : '';

		if ( ! empty( $assigned ) ) {

			$has_assignment[] = (int) $assigned;
		}
	}

	$absences = get_absences_array( $post_id );

	$has_assignment = array_merge( $has_assignment, $absences );

	$users = get_users( 'blog_id=' . get_current_blog_id() );

	foreach ( $users as $user ) {

		if ( ! in_array( $user->ID, $has_assignment ) ) {

			$ids[] = $user->ID;
		}
	}

	return $ids;

}



function wp4_format_contact( $userdata ) {

	$output = '';

	if ( empty( $userdata->last_name ) || ( $userdata->last_name == 'AVAILABLE' ) ) {

		return '';
	}

		$output .= "\n\n" . $userdata->first_name . ' ' . $userdata->last_name . "\n";

		$status = wp4t_get_member_status( $userdata->ID );

	if ( ! empty( $status ) ) {

		$output .= $status . "\n";
	}

	$contactmethods['home_phone'] = __( 'Home Phone', 'rsvpmaker-for-toastmasters' );

	$contactmethods['work_phone'] = __( 'Work Phone', 'rsvpmaker-for-toastmasters' );

	$contactmethods['mobile_phone'] = __( 'Mobile Phone', 'rsvpmaker-for-toastmasters' );

	$contactmethods['user_email'] = __( 'Email', 'rsvpmaker-for-toastmasters' );

	foreach ( $contactmethods as $name => $value ) {

		$trimmed = trim( $userdata->$name );

		if ( empty( $trimmed ) ) {

			continue;
		}

		if ( $name == 'user_email' ) {

			$output .= sprintf( '%s: <a href="mailto:%s">%s</a>' . "\n", $value, $trimmed, $trimmed );

		} elseif ( $name == 'status' ) {

			$output .= sprintf( "%s: %s\n", $value, $trimmed );

		} else {

			$phone = preg_replace( '/[^0-9\+]/', '', $trimmed );

			if ( strpos( $phone, '+' ) === false ) {

				$first_digit = substr( $phone, 0, 1 );

				if ( $first_digit != '1' ) {

					$phone = '1' . $phone;
				}

				$phone = '+' . $phone;

			}

			$output .= sprintf( '%s: <a href="tel:%s">%s</a>' . "\n", $value, $phone, $trimmed );

		}
	}

	return $output;

}



function wp4t_emails() {

	$list = '';

	$blogusers = get_users( 'blog_id=' . get_current_blog_id() );

	foreach ( $blogusers as $user ) {

		$email = $user->user_email;

		if ( strpos( $email, 'example.com' ) ) {

			continue;
		}

		if ( ! empty( $list ) ) {

			$list .= ',';
		}

		$list .= $email;

	}

	return $list;

}



function is_club_member() {

	return apply_filters( 'is_club_member', is_user_member_of_blog() );

}



function wpt_blocks_to_data( $content, $include_backup = true, $aggregate = false ) {

	$data = array();

	if ( strpos( $content, 'wp:wp4toast' ) ) {

		$blocks = preg_split( '/<!/', $content );

		foreach ( $blocks as $index => $block ) {

			if ( strpos( $block, 'agendanoterich2' ) ) {

				preg_match( '/{[^}]+}/', $block, $matches );

				if ( ! empty( $matches ) ) {

					$thisdata = (array) json_decode( $matches[0] );

					$thisdata['content'] = trim( strip_tags( '<' . $block . '>' ) );

					$thisdata['json'] = $matches[0];

					$key = $thisdata['uid'];

					$data[ $key ] = $thisdata;

				}
			} else {

				preg_match( '/{[^}]+}/', $block, $matches );

				if ( ! empty( $matches ) ) {

					$thisdata = (array) json_decode( $matches[0] );

					$thisdata['json'] = $matches[0];

					if ( ! empty( $thisdata['role'] ) ) {

						$key = $thisdata['role'];

						if ( $key == 'custom' ) {

							$key = $thisdata['role'] = $thisdata['custom_role'];
						}

						if ( ! $aggregate ) {

							$key .= ( empty( $thisdata['start'] ) ) ? 1 : $thisdata['start'];
						}
					} elseif ( ! empty( $thisdata['uid'] ) ) {

						$key = $thisdata['uid'];

					} else {
						$key = 'other' . $index;
					}

					$data[ $key ] = $thisdata;

				}

				if ( ! empty( $thisdata['backup'] ) && $include_backup && !empty($thisdata['role']) ) {

					$key = $backup['role'] = 'Backup ' . $thisdata['role'];

					$backup['count'] = 1;

					$data[ $key ] = $backup;

				}
			}
		}

		return $data;

	}

	preg_match_all( '/\[.+role="([^"]+).+\]/', $content, $matches );

	foreach ( $matches[1] as $index => $role ) {

		if ( strpos( $role, 'ackup' ) ) {

			continue;
		}

		preg_match( '/count="([\d]+)/', $matches[0][ $index ], $counts );

		$count = ( empty( $counts[1] ) ) ? 1 : $counts[1];

		$data[ $role ] = array(
			'role'  => $role,
			'count' => $count,
		);

	}

	return $data;

}



function wpt_blocks_to_data2( $content, $include_backup = true, $aggregate = false ) {

	$data = array();

	if ( strpos( $content, 'wp:wp4toast' ) ) {

		$blocks = preg_split( '/<!/', $content );

		foreach ( $blocks as $index => $block ) {

			if ( strpos( $block, 'agendanoterich2' ) ) {

				preg_match( '/{[^}]+}/', $block, $matches );

				if ( ! empty( $matches ) ) {

					$thisdata = (array) json_decode( $matches[0] );

					$thisdata['content'] = trim( strip_tags( '<' . $block . '>' ) );

					$thisdata['json'] = $matches[0];

					$data[] = $thisdata;

				}
			} else {

				preg_match( '/{[^}]+}/', $block, $matches );

				if ( ! empty( $matches ) ) {

					$thisdata = (array) json_decode( $matches[0] );

					$thisdata['json'] = $matches[0];

					if ( ! empty( $thisdata['role'] ) ) {

						$key = $thisdata['role'];

						if ( $key == 'custom' ) {

							$key = $thisdata['role'] = $thisdata['custom_role'];
						}

						if ( ! $aggregate ) {

							$key .= ( empty( $thisdata['start'] ) ) ? 1 : $thisdata['start'];
						}
					} elseif ( ! empty( $thisdata['uid'] ) ) {

						$key = $thisdata['uid'];

					} else {
						$key = 'other' . $index;
					}

					$data[] = $thisdata;

				}

				if ( ! empty( $thisdata['backup'] ) && $include_backup ) {

					$key = $backup['role'] = 'Backup ' . $thisdata['role'];

					$backup['count'] = 1;

					$data[] = $backup;

				}
			}
		}

		// printf('<pre>%s</pre>',var_export($data,true));

		return $data;

	}

	preg_match_all( '/\[.+role="([^"]+).+\]/', $content, $matches );

	foreach ( $matches[1] as $index => $role ) {

		if ( strpos( $role, 'ackup' ) ) {
			continue;
		}

		preg_match( '/count="([\d]+)/', $matches[0][ $index ], $counts );

		$count = ( empty( $counts[1] ) ) ? 1 : $counts[1];

		$data[ $role ] = array(
			'role'  => $role,
			'count' => $count,
		);

	}

	return $data;

}



// project data encoding

function make_tm_speechdata_array( $roledata, $manual, $project, $title, $intro ) {

	$roledata['manual'] = $manual;

	$roledata['project'] = $project;

	$roledata['title'] = $title;

	$roledata['intro'] = $intro;

	return $roledata;

}



function make_tm_roledata_array( $function = '' ) {

	global $current_user;

	return array(
		'time_recorded' => time(),
		'recorded_by'   => $current_user->user_login,
		'function'      => $function,
	);

}

function make_tm_usermeta_key( $role, $event_timestamp, $post_id ) {

	$slug = preg_replace( '/[^0-9]/', '', $role );

	$role = str_replace( 'Contest_Speaker', 'Speaker', $role );

	// Contest Speaker = Speaker

	if ( isset( $_GET['project_year'] ) ) {

		$slug = sanitize_text_field($_GET['project_year'] . $_GET['project_month'] . $_GET['project_day']);
	}

	return 'tm|' . trim( preg_replace( '/[^\sa-zA-Z]/', ' ', $role ) ) . '|' . $event_timestamp . '|' . $slug . '|' . sanitize_text_field($_SERVER['SERVER_NAME']) . '|' . $post_id;

}

function extract_usermeta_key_data( $key ) {

	$parts = explode( '|', $key );

	$data['role'] = $parts[1];

	$data['timestamp'] = $parts[2];

	$data['order'] = $parts[3];

	$data['domain'] = $parts[4];

	$data['post_id'] = $parts[5];

	return $data;

}



function cache_assignments( $post_id, $refresh = false ) {

	global $assign_cache;

	if ( $refresh ) {

		$assign_cache = array();

	} else {
		$assign_cache = get_transient( 'assign_cache' );
	}

	if ( empty( $assign_cache[ $post_id ] ) ) {

		$sql = "SELECT * FROM $wpdb->postmeta WHERE post_id=$post_id AND meta_value REGEXP '^[0-9]+$'";

		$results = $wpdb->get_results( $sql );

		foreach ( $results as $row ) {

			$assign_cache[ $post_id ][ $row->meta_key ] = $row->meta_value;

		}

		set_transient( 'assign_cache', $assign_cache, DAY_IN_SECONDS );

	}

}



function get_wpt_assignment( $post_id, $key ) {

	global $assign_cache;

	if ( isset( $assign_cache[ $post_id ][ $key ] ) ) {

		return $assign_cache[ $post_id ][ $key ];
	}

	return get_post_meta( $post_id, $key, true );

}



function set_wpt_assignment( $post_id, $key, $value, $update_cache = true ) {

	global $assign_cache;

	$assign_cache[ $post_id ][ $key ] = $value;

	update_post_meta( $post_id, $key, $value );

	if ( $update_cache ) { // unless we're told not to, update the cache transient

		set_transient( 'assign_cache', $assign_cache, DAY_IN_SECONDS );
	}

}



// do_action( 'add_user_to_blog', $user_id, $role, $blog_id );

add_action( 'add_user_to_blog', 'add_joined_club_date' );



function add_joined_club_date( $user_id ) {

	update_user_meta( $user_id, 'joined' . get_current_blog_id(), date( 'n/j/Y' ) );

}



function is_agenda_locked() {

	global $post;

	if ( is_admin() ) { // do not apply to the history screen

		return false;
	}

	$locked = false;

	$date = get_rsvp_date( $post->ID );

	$policy_lock = get_option( 'wpt_agenda_lock_policy' );

	if ( $policy_lock ) {

		$now = time();

		$string = $date . ' -' . $policy_lock . ' hours';

		$deadline = rsvpmaker_strtotime( $string );

		if ( $now > $deadline ) {

			$locked = true;
		}
	}

	if ( isset( $_GET['lock'] ) ) {

		$post_lock = sanitize_text_field($_GET['lock']);

		update_post_meta( $post->ID, 'agenda_lock', $post_lock );

	} else {
		$post_lock = get_post_meta( $post->ID, 'agenda_lock', true );
	}

	if ( $post_lock == 'unlockall' ) {

		$locked = false;

	} elseif ( ( $post_lock == 'unlockadmin' ) && current_user_can( 'edit_others_posts' ) ) {

		$locked = false;

	} elseif ( $post_lock == 'lockexceptadmin' ) {

		if ( current_user_can( 'edit_others_posts' ) ) {

			$locked = false;

		} else {
			$locked = true;
		}
	} elseif ( $post_lock == 'on' ) {

		$locked = true;
	}

	return $locked;

}

function get_agenda_timing( $post_id ) {

	global $rsvp_options;

	$time_format = str_replace( 'T', '', $rsvp_options['time_format'] );

	$post = get_post( $post_id );

	$date = get_rsvp_date( $post_id );

	$data = wpt_blocks_to_data( $post->post_content, false );

	$elapsed = 0;

	$time_array = array();

	foreach ( $data as $d ) {

		$t = strtotime( $date . ' +' . $elapsed . ' minutes' );

		$start_time_text = rsvpmaker_date( $time_format, $t );

		$start_time = $elapsed;

		$time_allowed = ( empty( $d['time_allowed'] ) ) ? 0 : (int) $d['time_allowed'];

		$padding_time = ( empty( $d['padding_time'] ) ) ? 0 : (int) $d['padding_time'];

		$add = $time_allowed + $padding_time;

		$elapsed += $add;

		if ( ! empty( $d['role'] ) ) {

				$start = ( empty( $d['start'] ) ) ? 1 : $d['start'];

				$index = str_replace( ' ', '_', $d['role'] ) . $start;

				$label = $d['role'];

		} elseif ( ! empty( $d['uid'] ) ) {

			$index = $d['uid'];

			$label = ( empty( $d['content'] ) ) ? $index : 'Note: ' . substr( trim( strip_tags( $d['content'] ) ), 0, 15 ) . '...';

		} else {
			continue;
		}

		$time_array[ $index ] = array(
			'label'        => $label,
			'start_time'   => $start_time,
			'elapsed'      => $elapsed,
			'time_allowed' => $time_allowed,
			'padding_time' => $padding_time,
		);

	}

	return $time_array;

}

function is_edit_roles() {

	if ( isset( $_GET['edit_roles'] ) ) {

		return true;
	}

	if ( isset( $_GET['page'] ) && ( $_GET['page'] == 'toastmasters_reconcile' ) ) {

		return true;
	}

	return false;

}



add_filter( 'wp_nav_menu', 'wp_nav_menu_wpt', 10, 2 );

function wp_nav_menu_wpt( $menu_html, $menu_args ) {
global $rsvp_options;
if ( strpos( $menu_html, '#rolesignup' ) || strpos( $menu_html, '#tmlogin' ) ) {

		$evlist = '';

		$future = future_toastmaster_meetings( 5 );

		if ( $future ) {

			$event = $future[0];

			$evlist = sprintf( '<li class="menu-item menu-item-type-post_type menu-item-object-rsvpmaker menu-item-%d menu-item-has-children" ><a href="%s">%s</a><ul class="sub-menu">', $event->ID, wpt_login_permalink( $event->ID ), __( 'Role Signup', 'rsvpmaker-for-toastmasters' ) );

			if ( ! empty( $future ) ) {

				foreach ( $future as $event ) {

					$evlist .= sprintf( '<li class="menu-item menu-item-type-post_type menu-item-object-rsvpmaker menu-item-%d"><a href="%s">%s</a></li>', $event->ID, wpt_login_permalink( $event->ID ), rsvpmaker_date($rsvp_options['long_date'],intval($event->ts_start)) );

				}
			}

			$evlist .= sprintf( '<li class="menu-item menu-item-type-post_type menu-item-object-rsvpmaker"><a href="%s">%s</a></li>', site_url( 'rsvpmaker/' ), __( 'Future Dates', 'rsvpmaker-for-toastmasters' ) );

			$evlist .= '</ul></li>';

		}
	}

	if ( strpos( $menu_html, '#rolesignup' ) ) {

		$menu_html = preg_replace( '/<li [^>]+><a[^"]+"#rolesignup[^<]+<\/a><\/li>/', $evlist, $menu_html );

	}

	if ( strpos( $menu_html, '#tmlogin' ) ) {

		add_option( 'wpt_login_menu_item', true );
		$button = apply_filters('tm_submenu_toggle_button','');

		$label = ( is_user_logged_in() ) ? __( 'Dashboard', 'rsvpmaker-for-toastmasters' ) : __( 'Login', 'rsvpmaker-for-toastmasters' );

		$toplink = ( is_user_logged_in() ) ? admin_url( '/' ) : wpt_login_permalink();

		$menu = '<li id="menu-item-wpt-login" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-has-children menu-item-wpt-login"><a href="' . $toplink . '">' . $label . '</a>'.$button.'<ul class="sub-menu">

		' . $evlist . '<li id="menu-item-profile" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-2862"><a href="' . admin_url( 'profile.php' ) . '">Profile</a></li>

			<li id="menu-item-profilephoto" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-2865"><a href="' . admin_url( 'profile.php#profilephoto' ) . '">Profile Photo</a></li>

			<li id="menu-item-password" class="menu-item menu-item-type-custom menu-item-object-custom menu-item-2863"><a href="' . admin_url( 'profile.php#password' ) . '">Password</a></li>

		</ul>

		</li>';

		$menu_html = preg_replace( '/<li [^>]+><a[^"]+"#tmlogin[^<]+<\/a><\/li>/', $menu, $menu_html );

	}
	return $menu_html;
}

function wpt_login_permalink( $id = 0, $permalink = '' ) {

	global $post;

	if ( empty( $id ) ) {

		$id = $post->ID;
	}

	if ( empty( $permalink ) ) {

		$permalink = get_permalink( $id );
	}

	if ( ! is_user_logged_in() ) {

		$permalink = wp_login_url( $permalink );
	}

	return $permalink;

}



function wpt_get_last_login( $user_id ) {

	global $rsvp_options;

	$slug = ( is_plugin_active( 'wordfence/wordfence.php' ) ) ? 'wfls-last-login' : 'wpt-last-login';

	$last = get_user_meta( $user_id, $slug, true );

	return rsvpmaker_date( $rsvp_options['long_date'] . ' ' . rsvp_options['time_format'], $last );

}



function wpt_set_last_login( $user_id ) {

	if ( is_plugin_active( 'wordfence/wordfence.php' ) ) {

		return; // don't duplicate function
	}

	update_post_meta( $user_id, 'wpt-last-login', time() );

}



function is_tm_officer( $user_id = 0 ) {

	global $current_user;

	if ( ! $user_id ) {

		$user_id = $current_user->ID;
	}

	$officers = get_option( 'wp4toastmasters_officer_ids' );

	if ( empty( $officers ) ) {

		return false;
	}

	return ! empty( $officers[ $user_id ] );

}



add_shortcode( 'time_planner_2020', 'time_planner_2020' );



function time_planner_minutes_select( $index, $minutes ) {

	$output = sprintf( '<select class="timeadjust" id="timeadjust%d" counter="%d">', $index, $index );

	for ( $i = 0; $i < 61; $i++ ) {

		$s = ( $i == $minutes ) ? ' selected="selected" ' : '';

		$output .= sprintf( '<option %s value="%s">%s</option>', $s, $i, $i );

	}

}

function time_planner_2020( $atts ) {

	global $post, $rsvp_options;

	$t = get_rsvpmaker_timestamp( $post_id );

	$output = sprintf( '<h3>Start at %s</h3>', date( 'H:i', $t ) );

	$addminutes = 0;

	$data = wpt_blocks_to_data2( $post->post_content );

	foreach ( $data as $index => $row ) {

		if ( ! empty( $row['role'] ) ) {

			$label = (($row['role'] == 'custom')) ? var_export($row,true) : $row['role'];
			$output .= var_export($row,true);
			$output .= sprintf( '<h3>%s %s</h3>', date( 'H:i', $t ), $label );

			$padding = ( empty( $row['padding_time'] ) ) ? '' : ' (including ' . $row['padding_time'] . ' minutes padding time)';

			$roleminutes = (int) $row['padding_time'] + (int) $row['time_allowed'];

			$output .= sprintf( '<p>%s minutes %s</p>', $roleminutes, $padding );

			$t += ( $roleminutes * 60 );

		} elseif ( ! empty( $row['time_allowed'] ) ) {

			$output .= sprintf( '<h3>%s %s</h3>', date( 'H:i', $t ), $row['content'] );

			$noteminutes = (int) $row['time_allowed'];

			$t += $noteminutes;

			$t += ( $noteminutes * 60 );

			$output .= sprintf( '<p>%s minutes</p>', $noteminutes );

		}
	}

	$output .= sprintf( '<h3>%s Done</h3>', date( 'H:i', $t ) );

	$output .= '<pre>' . var_export( $data, true ) . '</pre>';

	return $output;

}

function wp4t_is_district() {
    return (int) get_option('toastmasters_district');
}

function wp4t_editor_style_override() {
if(!(isset($_GET['post']) && isset($_GET['action'])))
	return;
global $post;
$special = get_post_meta(intval($_GET['post']),'_rsvpmaker_special',true);
if(($post->post_type != 'rsvpmailer') && ($special != 'Agenda Layout'))
	return;
?>
<style>
:root .editor-styles-wrapper {
    --global--color-background: #fff;
    --global--color-primary: #000;
    --global--color-secondary: #000;
    --button--color-background: #000;
    --button--color-text-hover: #333;
    --table--stripes-border-color: rgba(0, 0, 0, 0.15);
    --table--stripes-background-color: rgba(0, 0, 0, 0.15);
	background-color: #fff;
}
.editor-styles-wrapper {
	background-color: #fff;
	color: #000;
}}
</style>
<?php
}

add_action('admin_head','wp4t_editor_style_override');

function wp4t_name_index($user) {
	if(is_numeric($user))
		$name = get_member_name($user).$user;
	elseif(!empty($user->first_name) && !empty($user->last_name))
		$name = $user->first_name.$user->last_name.$user->ID;
	elseif(empty($user->display_name))
		return time();
	else
		$name = $user->display_name.$user->ID;
	return preg_replace('/[^a-z0-9]/','',strtolower($name));
}

//for integration with WP PayPal plugin
add_action('wp_paypal_ipn_processed','wp4t_wp_paypal_ipn_processed');
function wp4t_wp_paypal_ipn_processed($response) {
	mail('david@carrcommunications.com','paypal IPN Toastmasters',var_export($response,true));
}

function wptm_count_votes($post_id) {
	global $wpdb;
	$output = '';

	$open = get_post_meta($post_id,'openvotes');
	foreach($open as $v) {
		$addvote = get_post_meta($post_id,'addvote_'.$v,true);
		if(empty($addvote))
			$addvote = array();
		$votes[$v] = $addvote;
	}

	$sql = "SELECT * FROM $wpdb->postmeta where post_id=".$post_id." AND meta_key LIKE 'myvote%' ORDER BY meta_key, meta_value";
	$results = $wpdb->get_results($sql);
	foreach($results as $row) {
		$p = explode('_',$row->meta_key);
		$contest = $p[1];
		if(isset($votes[$contest][$row->meta_value]))
		$votes[$contest][$row->meta_value]++;
			else
		$votes[$contest][$row->meta_value] = 1;
	}



	if(!empty($votes)) {
		$output .= '<div id="votingresults"><h2>Voting Results as of '.rsvpmaker_date('H:i:s',time()).'</h2>';
		foreach($votes as $contest => $contestvote) {
			$label = get_post_meta($post_id,'votelabel_'.$contest,true);
			$ranking[$contest] = sprintf('<h3>Votes for %s</h3>',$label);
			if(empty($contestvote))
			$ranking[$contest] .= '<p>none</p>';
			else {
				arsort($contestvote);
				$count = 0;
				$last = 0;
				foreach($contestvote as $name => $score)
				{
					if(empty($winner[$contest]))
						$winner[$contest] = sprintf('%s: %s',$label,$name);
					if(($count == 1) && ($last == $score))
						$winner[$contest] .= ' (tie with '.$name.')';
					$ranking[$contest] .= sprintf('<p>%s: %s</p>',$name,$score);
					$last = $score;
					$count++;
				}
			}
	}
	foreach($winner as $w)
		$output .= '<p>'.$w.'</p>';
	foreach($ranking as $r)
		$output .= $r;
	$output .= '</div>';
	}
	return $output;
}

function wp4t_hour_past($post_id) {
	global $wpdb;
	$event_table = get_rsvpmaker_event_table();
	$end = (int) $wpdb->get_var("select ts_end from $event_table WHERE event=$post_id");
	if(!$end)
		return false;
	return (time() > ($end + DAY_IN_SECONDS));
}

function wp4t_evaluation_link($atts) {
	if(isset($atts['project']))
	{
		$get = $_GET;
		$get['project'] = preg_replace('/Level.+/',$atts['project'],$get['project']);
		$url = add_query_arg($get,admin_url('admin.php'));
		return sprintf('<a href="%s">%s</a>',$url,get_project_text($get['project']));
	}
}

if(!function_exists('get_rsvpmaker_timestamp')) {
	function get_rsvpmaker_timestamp( $post_id ) {
		$event = get_rsvpmaker_event($post_id);
		if(!empty($event) && !empty($event->ts_start))
			return intval($event->ts_start);
	}	
}

function wpt_rsvpmaker_admin_heading($headline, $function, $tag = '', $sidebar = '') {
	if(function_exists('rsvpmaker_admin_heading'))
		rsvpmaker_admin_heading($headline,$function,$tag,$sidebar);
	else
		echo '<h1>'.$headline.'</h1>';
}

add_filter('rsvpmaker-admin-heading-help','wpt_rsvpmaker_admin_heading_help',12,3);
function wpt_rsvpmaker_admin_heading_help($content,$function='',$tag='') {
	if($function == 'rsvpmaker_template_list') {
		$content .= '<p><a href="https://www.wp4toastmasters.com/knowledge-base/create-update-events-based-on-template/">Templates for Toastmasters Meetings</a></p>';
	}
	return $content; 
}