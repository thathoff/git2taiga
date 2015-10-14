<?php

namespace Blanko;

class Taiga
{

    private $url = "";
    private $authToken = "";
    private $project = "";

    public function __construct($taiga_url, $project)
    {
        $this->url = rtrim($taiga_url, "/") . "/api/v1/";
        $this->project = $project;
    }

    public function setAuthToken($token)
    {
        $this->authToken = $token;
    }

    public function loginUser($username, $password)
    {
        $result = $this->request("POST", "auth", array(
            "type" => "normal",
            "username" => $username,
            "password" => $password,
        ));

        if (isset($result->auth_token)) {
            $this->setAuthToken($result->auth_token);
            return true;
        }

        return false;
    }


    public function resolver($query = false)
    {
        if (!is_array($query)) {
            $query = array();
        }

        $query['project'] = $this->project;
        $request = "resolver?" . http_build_query($query);
        return $this->request("GET", $request);
    }

    public function closeRef($ref, $message = false)
    {
        $item = $this->getItemByRef($ref);

        if ($item) {
            $itemObject = $this->getItem($item['type'], $item['id']);
            $statuses = $this->getStatuses($item['type'], $item['project']);
            $close_status = $this->getCloseStatus($item['type'], $item['project'], $statuses);

            $current_status_index = false;
            $close_status_index = false;

            foreach ($statuses as $key => $status) {
                if ($itemObject->status == $status->id) {
                    $current_status_index = $key;
                }

                if ($close_status->id == $status->id) {
                    $close_status_index = $key;
                }

                if ($current_status_index && $close_status_index) {
                    break;
                }
            }

            // only update item when status is smaller than closed status
            if ($current_status_index < $close_status_index) {
                $values = array(
                    'status' => $close_status->id,
                    'version' => $itemObject->version,
                );

                if ($message) {
                    $values['comment'] = $message;
                }

                $this->patchItem($item['type'], $item['id'], $values);
            }

            return true;
        } else {
            return false;
        }
    }


    public function getStatuses($type, $project_id)
    {
        if ($type == "us") {
            $type = "userstory";
        }

        return $this->request("GET", $type . "-statuses?project=" . $project_id);
    }

    public function getItem($type, $id)
    {
        if ($type == "us") {
            $type = "userstories";
        } else {
            $type = $type . "s";
        }

        return $this->request("GET", $type . "/" . $id);
    }

    public function patchItem($type, $id, $data)
    {
        if ($type == "us") {
            $type = "userstories";
        } else {
            $type = $type . "s";
        }

        return $this->request("PATCH", $type . "/" . $id, $data);
    }

    public function getCloseStatus($type, $project_id, $statuses = false)
    {
        $statuses = $statuses ? $statuses : $this->getStatuses($type, $project_id);
        $status = false;

        foreach ($statuses as $status) {
            if ($status->is_closed) {
                break;
            }
        }

        return $status;
    }

    public function getItemByRef($ref)
    {
        $tries = array('us', 'issue', 'task');
        $result = false;

        foreach ($tries as $type) {
            $item = $this->resolver(array(
                $type => $ref,
            ));

            if (isset($item->$type)) {
                $result = array('id' => $item->$type, 'type' => $type, 'ref' => $ref, 'project' => $item->project);
                break;
            };
        }

        return $result;
    }

    private function request($method, $request, $data = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url . $request);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $header = array(
            "Content-Type: application/json",
        );

        if ($this->authToken) {
            $header[] = 'Authorization: Bearer ' . $this->authToken;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        // optional post data
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        //execute request
        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result);
        return $result;
    }
}
