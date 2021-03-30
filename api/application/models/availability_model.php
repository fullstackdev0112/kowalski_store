<?php

class Availability_model extends CI_Model {

    function __construct()
    {        
        parent::__construct();
        $this->load->helper('date');
    }	

	// different from get_availability. Returns the max number of rooms allowed to be sold per room type
	// as set by the hotel (does not subract those that are sold). May be more or less than the actual
	// number of rooms available to be sold.
    function get_inventory($start_date, $end_date, $room_types, $ota_id, $filter_can_be_sold_online=FALSE)
    {
		$room_types_string = implode(', ', $room_types);
		$can_be_sold_online_filter = $filter_can_be_sold_online ? 'AND r.can_be_sold_online = 1' : '';
    	
    	$query = $this->db->query("
			SELECT 
				di.date, 
				rt.id as room_type_id, 
				rt.name,

				(
					SELECT
						IFNULL((
							SELECT
								drxrtxc.availability
							from 
								date_range as dr,
								date_range_x_room_type as drxrt,
								date_range_x_room_type_x_channel as drxrtxc
							where 
								drxrtxc.date_range_x_room_type_id = drxrt.date_range_x_room_type_id AND
								drxrtxc.channel_id = ".$ota_id." AND
								rt.id = drxrt.room_type_id AND
								dr.date_range_id = drxrt.date_range_id AND
								dr.date_start <= di.date AND 
								di.date <= dr.date_end AND
								(
									(dr.sunday = '1' AND DAYOFWEEK(di.date) = '".SUNDAY."') OR
									(dr.monday = '1' AND DAYOFWEEK(di.date) = '".MONDAY."') OR
									(dr.tuesday = '1' AND DAYOFWEEK(di.date) = '".TUESDAY."') OR
									(dr.wednesday = '1' AND DAYOFWEEK(di.date) = '".WEDNESDAY."') OR
									(dr.thursday = '1' AND DAYOFWEEK(di.date) = '".THURSDAY."') OR
									(dr.friday = '1' AND DAYOFWEEK(di.date) = '".FRIDAY."') OR
									(dr.saturday = '1' AND DAYOFWEEK(di.date) = '".SATURDAY."')
								)
							order by drxrt.date_range_x_room_type_id DESC
							LIMIT 0,1
						), 999) - count(b.booking_id)
					FROM
						booking as b, 
						booking_block as brh,
						room as r
					WHERE
						b.source IN (".SOURCE_BOOKING_DOT_COM.",".SOURCE_EXPEDIA.",".SOURCE_AGODA.",".SOURCE_MYALLOCATOR.",".SOURCE_SITEMINDER.") AND
						r.room_type_id = rt.id AND
						brh.room_id = r.room_id AND
						brh.check_out_date > di.date AND 
						di.date >= brh.check_in_date AND
						b.booking_id = brh.booking_id AND
						b.state < 4 AND
						b.is_deleted = '0' AND
						r.is_deleted = '0'
				) as availability
			FROM
				room_type as rt,
				date_interval as di,
				room as r
			WHERE
				rt.id IN (".$room_types_string.") AND
				r.room_type_id = rt.id AND
				di.date >= '$start_date' AND 
				'$end_date' >= di.date AND
				rt.is_deleted = '0' AND
				r.is_deleted = '0'
				".$can_be_sold_online_filter."

			GROUP BY rt.id, di.date
			ORDER BY di.date ASC
		");

		$result_array = Array();
		if ($this->db->_error_message())
		{
			show_error($this->db->_error_message());
		} else  {// otherwise, return insert_id;
			$result_array = $query->result_array();
		}

		$grouped_by_room_type = array();
		// organize the array into room_types
		foreach ($result_array as $availability)
		{
			$grouped_by_room_type[$availability['room_type_id']][] = Array(
				'date' => $availability['date'],
				'availability' => $availability['availability']
				);
		}

		$date_ranged_array = array();

		foreach ($grouped_by_room_type as $key => $array_of_room_type)
		{
			$date_ranged_array[$key] = get_array_with_range_of_dates(
																$array_of_room_type,
																$ota_id);
		};
      
		//return $grouped_by_room_type;
		return $date_ranged_array;
    }

	// net availability
    function get_availability($start_date, $end_date, $room_types, $ota_id, $filter_can_be_sold_online=FALSE, $adult_count = null, $children_count = null, $get_inventory = false, $get_max_availability = true, $get_inventorysold = true, $get_closeout_status = true, $is_overbooking = false, $company_id = null)
    {
    	$room_types_string = implode(', ', $room_types);
		$can_be_sold_online_filter = $filter_can_be_sold_online ? 'AND r.can_be_sold_online = 1' : '';
    	
        $adult_count_sql = $adult_count ? "rt.max_adults >= $adult_count AND " : "";
        $children_count_sql = $children_count ? "rt.max_children >= $children_count AND " : "";
        $max_occupancy_sql = $min_occupancy_sql = "";
        if ($adult_count || $children_count) {
            $total_occupants = (int)$adult_count + (int)$children_count;
            $max_occupancy_sql = "rt.max_occupancy >= $total_occupants AND ";
            $min_occupancy_sql = "rt.min_occupancy <= $total_occupants AND ";
        }

        $inventory_select = $inventory_join = "";
        if ($get_inventory) {
			
			if($get_max_availability)
			{
				$inventory_select = "IF(tresult.max_availability < 0, 0, tresult.max_availability) AS max_availability, ";
				$inventory_join = "LEAST(
							(
								SELECT
									IFNULL((
										SELECT
											drxrtxc.availability 
										from 
											date_range as dr,
											date_range_x_room_type as drxrt,
											date_range_x_room_type_x_channel as drxrtxc
										where 
											drxrtxc.date_range_x_room_type_id = drxrt.date_range_x_room_type_id AND
											drxrtxc.channel_id = ".$ota_id." AND
											rt.id = drxrt.room_type_id AND
											dr.date_range_id = drxrt.date_range_id AND
											dr.date_start <= di.date AND 
											di.date <= dr.date_end AND
											(
												(dr.sunday = '1' AND DAYOFWEEK(di.date) = '".SUNDAY."') OR
												(dr.monday = '1' AND DAYOFWEEK(di.date) = '".MONDAY."') OR
												(dr.tuesday = '1' AND DAYOFWEEK(di.date) = '".TUESDAY."') OR
												(dr.wednesday = '1' AND DAYOFWEEK(di.date) = '".WEDNESDAY."') OR
												(dr.thursday = '1' AND DAYOFWEEK(di.date) = '".THURSDAY."') OR
												(dr.friday = '1' AND DAYOFWEEK(di.date) = '".FRIDAY."') OR
												(dr.saturday = '1' AND DAYOFWEEK(di.date) = '".SATURDAY."')
											)
										order by drxrt.date_range_x_room_type_id DESC
										LIMIT 0,1
									), 999)
							),
							count(r.room_id)
                    ) AS max_availability, ";
			}
			if($get_inventorysold)
			{
				$inventory_select .= " IF(tresult.inventory_sold < 0, 0, tresult.inventory_sold) AS inventory_sold,";
				$inventory_join .= "(
							(
								SELECT
									count(DISTINCT b.booking_id)
								FROM
									booking as b, 
									booking_block as brh,
									room as r,
									company as c
								WHERE
									DATE(brh.check_out_date) > di.date AND 
									di.date >= DATE(brh.check_in_date) AND
									b.booking_id = brh.booking_id AND
									c.company_id = b.company_id AND
									IF(c.book_over_unconfirmed_reservations = 1, b.state < 4, (b.state < 4 OR b.state = 7)) AND
									b.is_deleted = '0' AND
									".(($ota_id == -1) ? "" : "b.source IN (".$ota_id.") AND")."
									r.room_type_id = rt.id AND
									brh.room_id = r.room_id AND
									r.is_deleted = '0' $can_be_sold_online_filter
							) + (
								SELECT
									count(DISTINCT b.booking_id)
								FROM
									booking as b, 
									booking_block as brh,
									company as c
								WHERE
									DATE(brh.check_out_date) > di.date AND 
									di.date >= DATE(brh.check_in_date) AND
									b.booking_id = brh.booking_id AND
									c.company_id = b.company_id AND
									IF(c.book_over_unconfirmed_reservations = 1, b.state < 4, (b.state < 4 OR b.state = 7)) AND
									b.is_deleted = '0' AND
									".(($ota_id == -1) ? "" : "b.source IN (".$ota_id.") AND")."
									brh.room_type_id = rt.id AND
									(brh.room_id IS NULL OR brh.room_id = 0)
							)
                    ) AS inventory_sold,";
			}
        }
		
		$get_closeout_status_select = $get_closeout_status_join = "";
		if($get_closeout_status)
		{
			$get_closeout_status_select = "IF(tresult.closeout_status < 0, 0, tresult.closeout_status) AS closeout_status, ";
			$get_closeout_status_join = ", IFNULL(
											(
												SELECT
													IFNULL((
														SELECT
															drxrtxs.can_be_sold_online
														from 
															date_range as dr,
															date_range_x_room_type as drxrt,
															date_range_x_room_type_x_status as drxrtxs
														where 
															drxrtxs.date_range_x_room_type_id = drxrt.date_range_x_room_type_id AND
															drxrtxs.channel_id = ".$ota_id." AND
															rt.id = drxrt.room_type_id AND
															dr.date_range_id = drxrt.date_range_id AND
															dr.date_start <= di.date AND 
															di.date <= dr.date_end AND
															(
																(dr.sunday = '1' AND DAYOFWEEK(di.date) = '".SUNDAY."') OR
																(dr.monday = '1' AND DAYOFWEEK(di.date) = '".MONDAY."') OR
																(dr.tuesday = '1' AND DAYOFWEEK(di.date) = '".TUESDAY."') OR
																(dr.wednesday = '1' AND DAYOFWEEK(di.date) = '".WEDNESDAY."') OR
																(dr.thursday = '1' AND DAYOFWEEK(di.date) = '".THURSDAY."') OR
																(dr.friday = '1' AND DAYOFWEEK(di.date) = '".FRIDAY."') OR
																(dr.saturday = '1' AND DAYOFWEEK(di.date) = '".SATURDAY."')
															)
														order by drxrt.date_range_x_room_type_id DESC
														LIMIT 0,1
													), 1)
											),
											1
										) AS closeout_status ";
		}

        $check_out_date_label = "IF(rt.prevent_inline_booking = 1, DATE(brh.check_out_date) + INTERVAL 1 DAY, DATE(brh.check_out_date))";
        $check_in_date_label = "IF(rt.prevent_inline_booking = 1, DATE(brh.check_in_date) - INTERVAL 1 DAY, DATE(brh.check_in_date))";

        $count_distinct_bookings = "count(DISTINCT IF(rt.prevent_inline_booking = 1, r.room_id, b.booking_id))";

        // a hack to fix availability for Dublin motel
        $unassigned_bookings_count_sql = $company_id == "8490013980" ? "" :
            "- (
                                SELECT
                                    count(DISTINCT b.booking_id)
                                FROM
                                    booking as b, 
                                    booking_block as brh,
                                    company as c
                                WHERE
                                    DATE(brh.check_out_date) > di.date AND 
                                    di.date >= DATE(brh.check_in_date) AND
                                    b.booking_id = brh.booking_id AND
                                    c.company_id = b.company_id AND
                                    IF(c.book_over_unconfirmed_reservations = 1, b.state < 4, (b.state < 4 OR b.state = 7)) AND
                                    b.is_deleted = '0' AND
                                    brh.room_type_id = rt.id AND
                                    (brh.room_id IS NULL OR brh.room_id = 0)
                            )";


        $sql = "
                SELECT 
                tresult.date,
                tresult.room_type_id,
                tresult.name,
                $inventory_select
                $get_closeout_status_select
				IF(tresult.availability < 0, 0, tresult.availability) AS availability
            FROM 
            (
                SELECT 
                    di.date, 
                    rt.id as room_type_id, 
                    rt.name,
                    $inventory_join
                    GREATEST
                    (
                        0,

                        LEAST(
                            # Availability Among ALL bookings
                            count(r.room_id) - (
                                SELECT
                                    $count_distinct_bookings
                                FROM
                                    booking as b, 
                                    booking_block as brh,
                                    room as r,
                                    company as c
                                WHERE
                                    $check_out_date_label > di.date AND 
                                    di.date >= $check_in_date_label AND
                                    b.booking_id = brh.booking_id AND
                                    c.company_id = b.company_id AND
                                    IF(c.book_over_unconfirmed_reservations = 1, b.state < 4, (b.state < 4 OR b.state = 7)) AND
                                    b.is_deleted = '0' AND
                                    r.room_type_id = rt.id AND
                                    brh.room_id = r.room_id AND
                                    r.is_deleted = '0' $can_be_sold_online_filter
                            ) 
                            $unassigned_bookings_count_sql,

                            # Availability for OTA bookings
                            (
                                (
									SELECT(IFNULL((
										SELECT
											drxrtxc.availability
										from 
											date_range as dr,
											date_range_x_room_type as drxrt,
											date_range_x_room_type_x_channel as drxrtxc
										where 
											drxrtxc.date_range_x_room_type_id = drxrt.date_range_x_room_type_id AND
											drxrtxc.channel_id = ".$ota_id." AND
											rt.id = drxrt.room_type_id AND
											dr.date_range_id = drxrt.date_range_id AND
											dr.date_start <= di.date AND 
											di.date <= dr.date_end AND
											(
												(dr.sunday = '1' AND DAYOFWEEK(di.date) = '".SUNDAY."') OR
												(dr.monday = '1' AND DAYOFWEEK(di.date) = '".MONDAY."') OR
												(dr.tuesday = '1' AND DAYOFWEEK(di.date) = '".TUESDAY."') OR
												(dr.wednesday = '1' AND DAYOFWEEK(di.date) = '".WEDNESDAY."') OR
												(dr.thursday = '1' AND DAYOFWEEK(di.date) = '".THURSDAY."') OR
												(dr.friday = '1' AND DAYOFWEEK(di.date) = '".FRIDAY."') OR
												(dr.saturday = '1' AND DAYOFWEEK(di.date) = '".SATURDAY."')
											)
										order by drxrt.date_range_x_room_type_id DESC
										LIMIT 0,1
									), 999)) - $count_distinct_bookings
									FROM
										booking as b, 
										booking_block as brh,
										room as r
									WHERE
										".(($ota_id == -1) ? "" : "b.source IN (".$ota_id.") AND")."
										r.room_type_id = rt.id AND
										brh.room_id = r.room_id AND
										$check_out_date_label > di.date AND 
										di.date >= $check_in_date_label AND
										b.booking_id = brh.booking_id AND
										b.state < 4 AND
										b.is_deleted = '0' AND
										r.is_deleted = '0'
								) - (
									SELECT
										count(DISTINCT b.booking_id)
									FROM
										booking as b, 
										booking_block as brh
									WHERE
										".(($ota_id == -1) ? "" : "b.source IN (".$ota_id.") AND")."
										brh.room_type_id = rt.id AND
										(brh.room_id IS NULL OR brh.room_id = 0) AND
										DATE(brh.check_out_date) > di.date AND 
										di.date >= DATE(brh.check_in_date) AND
										b.booking_id = brh.booking_id AND
										b.state < 4 AND
										b.is_deleted = '0'
								)
                            )
                        )

                    ) 
                    - (IF(rt.ota_close_out_threshold AND 
                        ('$ota_id' = ".SOURCE_BOOKING_DOT_COM." OR '$ota_id' = ".SOURCE_MYALLOCATOR." OR '$ota_id' = ".SOURCE_EXPEDIA." OR '$ota_id' = ".SOURCE_AGODA."  OR '$ota_id' = ".SOURCE_SITEMINDER."), 
                        rt.ota_close_out_threshold, 0)) as availability
					$get_closeout_status_join  
                FROM
                    date_interval as di,
                    room_type as rt
                LEFT JOIN room as r ON r.room_type_id = rt.id AND r.is_deleted = '0' ".$can_be_sold_online_filter."
                WHERE
                    rt.id IN (".$room_types_string.") AND                    
                    $adult_count_sql       
                    $children_count_sql 
                    $max_occupancy_sql
                    $min_occupancy_sql
                    di.date >= '$start_date' AND 
                    '$end_date' >= di.date AND
                    rt.is_deleted = '0' 
                    

                GROUP BY rt.id, di.date
                ORDER BY di.date ASC 
            ) as tresult
		";

//		echo $sql; die;

		$query = $this->db->query($sql);

		if (isset($_GET['dev_mode'])) {
            echo $this->db->last_query();
        }

		$result_array = Array();
		if ($this->db->_error_message())
		{
			show_error($this->db->_error_message());
		} else  {// otherwise, return insert_id;
			$result_array = $query->result_array();
		}

		if($is_overbooking)
		{
			return $result_array;
		}

		$grouped_by_room_type = array();
		// organize the array into room_types
		foreach ($result_array as $availability)
		{
            if ($get_inventory) {
                $grouped_by_room_type[$availability['room_type_id']][] = Array(
                        'date' => $availability['date'],
                        'availability' => isset($availability['availability']) ? $availability['availability'] : null,
                        'max_availability' => isset($availability['max_availability']) ? $availability['max_availability'] : null,
                        'inventory_sold' => isset($availability['inventory_sold']) ? $availability['inventory_sold'] : null,
                        'closeout_status' => isset($availability['closeout_status']) ? $availability['closeout_status'] : null
                    );
            } else {
                $grouped_by_room_type[$availability['room_type_id']][] = Array(
                        'date' => $availability['date'],
                        'availability' => isset($availability['availability']) ? $availability['availability'] : null,
                        'closeout_status' => isset($availability['closeout_status']) ? $availability['closeout_status'] : null
                    );
            }
		}
        
		$date_ranged_array = array();

        if (isset($_GET['dev_mode'])) {
            print_r($grouped_by_room_type);
        }

		foreach ($grouped_by_room_type as $key => $array_of_room_type)
		{
			$date_ranged_array[$key] = get_array_with_range_of_dates(
																$array_of_room_type,
																$ota_id);
		};
       
		return $date_ranged_array;

    }

	// max availability set by hotels
    function get_max_availability($start_date, $end_date, $room_types, $channel, $filter_can_be_sold_online=FALSE)
    {
    	$room_types_string = implode(', ', $room_types);
		$can_be_sold_online_filter = $filter_can_be_sold_online ? 'AND r.can_be_sold_online = 1' : '';
    	
    	$query = $this->db->query("
			SELECT 
				di.date, 
				rt.id as room_type_id, 
				rt.name,
				 
				(
					(
					SELECT
						(IFNULL((
							SELECT
								drxrtxc.availability
							from 
								date_range as dr,
								date_range_x_room_type as drxrt,
								date_range_x_room_type_x_channel as drxrtxc
							where 
								drxrtxc.date_range_x_room_type_id = drxrt.date_range_x_room_type_id AND
								drxrtxc.channel_id = ".$channel." AND
								rt.id = drxrt.room_type_id AND
								dr.date_range_id = drxrt.date_range_id AND
								dr.date_start <= di.date AND 
								di.date <= dr.date_end AND
								(
									(dr.sunday = '1' AND DAYOFWEEK(di.date) = '".SUNDAY."') OR
									(dr.monday = '1' AND DAYOFWEEK(di.date) = '".MONDAY."') OR
									(dr.tuesday = '1' AND DAYOFWEEK(di.date) = '".TUESDAY."') OR
									(dr.wednesday = '1' AND DAYOFWEEK(di.date) = '".WEDNESDAY."') OR
									(dr.thursday = '1' AND DAYOFWEEK(di.date) = '".THURSDAY."') OR
									(dr.friday = '1' AND DAYOFWEEK(di.date) = '".FRIDAY."') OR
									(dr.saturday = '1' AND DAYOFWEEK(di.date) = '".SATURDAY."')
								)
							order by drxrt.date_range_x_room_type_id DESC
							LIMIT 0,1
						), 999)) - count(DITINCT b.booking_id)
						FROM
							booking as b, 
							booking_block as brh,
							room as r
						WHERE
							b.source IN (".SOURCE_BOOKING_DOT_COM.",".SOURCE_EXPEDIA.",".SOURCE_AGODA.",".SOURCE_SITEMINDER.") AND
							r.room_type_id = rt.id AND
							brh.room_id = r.room_id AND
							brh.check_out_date > di.date AND 
							di.date >= brh.check_in_date AND
							b.booking_id = brh.booking_id AND
							b.state < 4 AND
							b.is_deleted = '0' AND
							r.is_deleted = '0'
					) - (
						SELECT
							count(DITINCT b.booking_id)
						FROM
							booking as b, 
							booking_block as brh
						WHERE
							b.source IN (".SOURCE_BOOKING_DOT_COM.",".SOURCE_EXPEDIA.",".SOURCE_AGODA.",".SOURCE_SITEMINDER.") AND
							brh.room_type_id = rt.id AND
							(brh.room_id IS NULL OR brh.room_id = 0) AND
							brh.check_out_date > di.date AND 
							di.date >= brh.check_in_date AND
							b.booking_id = brh.booking_id AND
							b.state < 4 AND
							b.is_deleted = '0'
					)
				)
				as availability
			FROM
				date_interval as di,
				room_type as rt
			LEFT JOIN room as r ON r.room_type_id = rt.id AND r.is_deleted = '0' ".$can_be_sold_online_filter."
			WHERE
				rt.id IN (".$room_types_string.") AND
				
				di.date >= '$start_date' AND 
				'$end_date' >= di.date AND
				rt.is_deleted = '0'
				

			GROUP BY rt.id, di.date
			ORDER BY di.date ASC
		");

		$result_array = Array();
		if ($this->db->_error_message())
		{
			show_error($this->db->_error_message());
		} else  {// otherwise, return insert_id;
			$result_array = $query->result_array();
		}
		
		//echo $this->db->last_query();

		$grouped_by_room_type = array();
		// organize the array into room_types
		foreach ($result_array as $availability)
		{
			$grouped_by_room_type[$availability['room_type_id']][] = Array(
				'date' => $availability['date'],
				'availability' => $availability['availability']
				);
		}

		$date_ranged_array = array();

		foreach ($grouped_by_room_type as $key => $array_of_room_type)
		{
			$date_ranged_array[$key] = get_array_with_range_of_dates(
																$array_of_room_type,
																$channel);
		};

		return $date_ranged_array;

    }

	// called from get_availability_by_room_type
	// return number of occupancies grouped by room type
	function get_overall_occupancy($company_id, $start_date, $end_date)
	{
		$query = $this->db->query("			
			SELECT indexes.room_type_id, indexes.selling_date, count(occupancy.booking_id) as occupancies
			FROM 
			(
				SELECT d.date as selling_date, rt.id as room_type_id
				FROM 
					date_interval as d, room_type as rt
				WHERE 	
					rt.company_id = '$company_id' AND
					d.date >= '$start_date' AND 
					'$end_date' >= d.date AND
					rt.is_deleted = '0'
			) indexes
			LEFT JOIN
			(
				SELECT
					rt2.id as room_type_id, b.booking_id, 
					GREATEST(brh.check_in_date, '$start_date') as check_in_date, 
					LEAST(brh.check_out_date, '$end_date') as check_out_date
				FROM
					booking as b, 
					room as r,
					room_type as rt2,
					booking_block as brh
				WHERE
					brh.check_out_date > '$start_date' AND '$end_date' > brh.check_in_date AND
					b.booking_id = brh.booking_id AND
					b.state < 4 AND
					b.is_deleted = '0' AND
					brh.check_in_date < brh.check_out_date AND
					brh.room_id = r.room_id AND
					r.room_type_id = rt2.id AND
					rt2.is_deleted = '0' AND
					rt2.company_id = '$company_id'
			) occupancy ON 
				occupancy.check_in_date <= indexes.selling_date AND 
				occupancy.check_out_date > indexes.selling_date AND
				occupancy.room_type_id = indexes.room_type_id
			GROUP BY indexes.room_type_id, indexes.selling_date
		");
		
		if ($this->db->_error_message())
		{
			show_error($this->db->_error_message());
		} else  {// otherwise, return insert_id;
			return $query->result_array();
		}
	}
	
	/**
	*	Returns array of availabilities grouped by Room Type
	*	@return array
	*/
	function get_room_count_per_room_type($company_id)
	{
		// First, get the total number of rooms per room type
		$query = $this->db->query("			
			SELECT rt.id, count(r.room_id) as room_count
			FROM room_type as rt
			LEFT JOIN room as r ON r.room_type_id = rt.id AND r.is_deleted = '0'
			WHERE
				rt.company_id = '$company_id' AND
				rt.is_deleted = '0'
			GROUP BY rt.id
		");
		
		if ($this->db->_error_message())
		{
			show_error($this->db->_error_message());
		}
		
		$result = $query->result_array();

		return $result;
	}
	
}
