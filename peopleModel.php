<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

class peopleModel extends Model {
  public $cachable = array('is_friend', 'get_person', 'get_person_info', 'get_friends', 'get_friends_count', 'get_friend_requests');

  public function load_is_friend($person_id, $friend_id) {
    global $db;
    $this->add_dependency('people', $person_id);
    $this->add_dependency('people', $friend_id);
    $person_id = $db->addslashes($person_id);
    $friend_id = $db->addslashes($friend_id);
    $res = $db->query("select * from friends where (person_id = $person_id and friend_id = $friend_id) or (person_id = $friend_id and friend_id = $person_id)");
    // return 0 instead of false, not to trip up the caching layer (who does a binary === false compare on data, so 0 == false but not === false)
    return $db->num_rows($res) != 0 ? true : 0;
  }
  
  // if extended = true, it also queries all child tables
  // defaults to false since its a hell of a presure on the database.
  // remove once we add some proper caching
  public function load_get_person($id, $extended = false) {
    global $db;
    $this->add_dependency('people', $id);
    $id = $db->addslashes($id);
    $res = $db->query("select * from persons where id = $id");
    if (! $db->num_rows($res)) {
      throw new Exception("Invalid person");
    }
    $person = $db->fetch_array($res, MYSQLI_ASSOC);
    //TODO missing : person_languages_spoken, need to add table with ISO 639-1 codes
    $tables_addresses = array('person_addresses', 'person_current_location');
    $tables_organizations = array('person_jobs', 'person_schools');
    $tables = array('person_activities', 'person_body_type', 'person_books', 'person_cars',
        'person_emails', 'person_food', 'person_heroes', 'person_movies',
        'person_interests', 'person_music', 'person_phone_numbers', 'person_quotes',
        'person_sports', 'person_tags', 'person_turn_offs', 'person_turn_ons',
        'person_tv_shows', 'person_urls');
    foreach ($tables as $table) {
      $person[$table] = array();
      $res = $db->query("select * from $table where person_id = $id");
      while ($data = $db->fetch_array($res, MYSQLI_ASSOC)) {
        $person[$table][] = $data;
      }
    }
    foreach ($tables_addresses as $table) {
      $res = $db->query("select addresses.* from addresses, $table where $table.person_id = $id and addresses.id = $table.address_id");
      while ($data = $db->fetch_array($res)) {
        $person[$table][] = $data;
      }
    }
    foreach ($tables_organizations as $table) {
      $res = $db->query("select organizations.* from organizations, $table where $table.person_id = $id and organizations.id = $table.organization_id");
      while ($data = $db->fetch_array($res)) {
        $person[$table][] = $data;
      }
    }
    return $person;
  }

  public function remove_friend($person_id, $friend_id) {
    global $db;
    $this->invalidate_dependency('people', $person_id);
    $this->invalidate_dependency('people', $friend_id);
    $person_id = $db->addslashes($person_id);
    $friend_id = $db->addslashes($friend_id);
    $res = $db->query("delete from friends where (person_id = $person_id and friend_id = $friend_id) or (person_id = $friend_id and friend_id = $person_id)");
    return $db->affected_rows($res) != 0;
  }
  
  public function load_get_friend_requests($id) {
    global $db;
    $this->add_dependency('friendrequest', $id);
    $requests = array();
    $friend_id = $db->addslashes($id);
    $res = $db->query("select person_id from friend_requests where friend_id = $friend_id");
    while (list($friend_id) = $db->fetch_row($res)) {
      $requests[$friend_id] = $this->get_person($friend_id, false);
    }
    return $requests;
  }

  public function set_profile_photo($id, $url) {
    global $db;
    $this->invalidate_dependency('people', $id);
    $id = $db->addslashes($id);
    $url = $db->addslashes($url);
    $db->query("update persons set thumbnail_url = '$url' where id = $id");
  }

  public function save_person($id, $person) {
    global $db;
    $this->invalidate_dependency('people', $id);
    $id = $db->addslashes($id);
    $supported_fields = array('about_me', 'children', 'birthday', 'drinker', 'ethnicity', 'fashion',
        'gender', 'happiest_when', 'humor', 'job_interests', 'living_arrangement',
        'looking_for', 'nickname', 'pets', 'political_views', 'profile_song',
        'profile_video', 'relationship_status', 'religion', 'romance', 'scared_of',
        'sexual_orientation', 'smoker', 'status', 'utc_offset', 'first_name',
        'last_name');
    foreach ($person as $key => $val) {
      if (in_array($key, $supported_fields)) {
        if ($val == '-') {
          $updates[] = "`" . $db->addslashes($key) . "` = null";
        } else {
          $updates[] = "`" . $db->addslashes($key) . "` = '" . $db->addslashes($val) . "'";
        }
      }
    }
    if (count($updates)) {
      $query = "update persons set " . implode(', ', $updates) . " where id = $id";
      $db->query($query);
    }
  }
  
  public function search($name) {
    global $db;
    $name = $db->addslashes($name);
    $ret = array();
    $res = $db->query("select id, email, first_name, last_name from persons where concat(first_name, ' ', last_name) like '%$name%' or email like '%$name%'");
    while ($row = $db->fetch_array($res, MYSQLI_ASSOC)) {
      $ret[] = $row;
    }
    return $ret;
  }
}