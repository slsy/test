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

}