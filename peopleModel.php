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
  
  public function remove_friend($person_id, $friend_id) {
    global $db;
    $this->invalidate_dependency('people', $person_id);
    $this->invalidate_dependency('people', $friend_id);
    $person_id = $db->addslashes($person_id);
    $friend_id = $db->addslashes($friend_id);
    $res = $db->query("delete from friends where (person_id = $person_id and friend_id = $friend_id) or (person_id = $friend_id and friend_id = $person_id)");
    return $db->affected_rows($res) != 0;
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

}