<?php
/*
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
namespace Ecs\Request\V20140526;

class RevokeSecurityGroupEgressRequest extends \RpcAcsRequest
{
	function  __construct()
	{
		parent::__construct("Ecs", "2014-05-26", "RevokeSecurityGroupEgress", "ecs", "openAPI");
		$this->setMethod("POST");
	}

	private  $nicType;

	private  $resourceOwnerId;

	private  $sourcePortRange;

	private  $clientToken;

	private  $securityGroupId;

	private  $description;

	private  $ipv6DestCidrIp;

	private  $ipv6SourceCidrIp;

	private  $policy;

	private  $portRange;

	private  $resourceOwnerAccount;

	private  $ipProtocol;

	private  $ownerAccount;

	private  $sourceCidrIp;

	private  $destGroupId;

	private  $ownerId;

	private  $destGroupOwnerAccount;

	private  $priority;

	private  $destCidrIp;

	private  $destGroupOwnerId;

	public function getNicType() {
		return $this->nicType;
	}

	public function setNicType($nicType) {
		$this->nicType = $nicType;
		$this->queryParameters["NicType"]=$nicType;
	}

	public function getResourceOwnerId() {
		return $this->resourceOwnerId;
	}

	public function setResourceOwnerId($resourceOwnerId) {
		$this->resourceOwnerId = $resourceOwnerId;
		$this->queryParameters["ResourceOwnerId"]=$resourceOwnerId;
	}

	public function getSourcePortRange() {
		return $this->sourcePortRange;
	}

	public function setSourcePortRange($sourcePortRange) {
		$this->sourcePortRange = $sourcePortRange;
		$this->queryParameters["SourcePortRange"]=$sourcePortRange;
	}

	public function getClientToken() {
		return $this->clientToken;
	}

	public function setClientToken($clientToken) {
		$this->clientToken = $clientToken;
		$this->queryParameters["ClientToken"]=$clientToken;
	}

	public function getSecurityGroupId() {
		return $this->securityGroupId;
	}

	public function setSecurityGroupId($securityGroupId) {
		$this->securityGroupId = $securityGroupId;
		$this->queryParameters["SecurityGroupId"]=$securityGroupId;
	}

	public function getDescription() {
		return $this->description;
	}

	public function setDescription($description) {
		$this->description = $description;
		$this->queryParameters["Description"]=$description;
	}

	public function getIpv6DestCidrIp() {
		return $this->ipv6DestCidrIp;
	}

	public function setIpv6DestCidrIp($ipv6DestCidrIp) {
		$this->ipv6DestCidrIp = $ipv6DestCidrIp;
		$this->queryParameters["Ipv6DestCidrIp"]=$ipv6DestCidrIp;
	}

	public function getIpv6SourceCidrIp() {
		return $this->ipv6SourceCidrIp;
	}

	public function setIpv6SourceCidrIp($ipv6SourceCidrIp) {
		$this->ipv6SourceCidrIp = $ipv6SourceCidrIp;
		$this->queryParameters["Ipv6SourceCidrIp"]=$ipv6SourceCidrIp;
	}

	public function getPolicy() {
		return $this->policy;
	}

	public function setPolicy($policy) {
		$this->policy = $policy;
		$this->queryParameters["Policy"]=$policy;
	}

	public function getPortRange() {
		return $this->portRange;
	}

	public function setPortRange($portRange) {
		$this->portRange = $portRange;
		$this->queryParameters["PortRange"]=$portRange;
	}

	public function getResourceOwnerAccount() {
		return $this->resourceOwnerAccount;
	}

	public function setResourceOwnerAccount($resourceOwnerAccount) {
		$this->resourceOwnerAccount = $resourceOwnerAccount;
		$this->queryParameters["ResourceOwnerAccount"]=$resourceOwnerAccount;
	}

	public function getIpProtocol() {
		return $this->ipProtocol;
	}

	public function setIpProtocol($ipProtocol) {
		$this->ipProtocol = $ipProtocol;
		$this->queryParameters["IpProtocol"]=$ipProtocol;
	}

	public function getOwnerAccount() {
		return $this->ownerAccount;
	}

	public function setOwnerAccount($ownerAccount) {
		$this->ownerAccount = $ownerAccount;
		$this->queryParameters["OwnerAccount"]=$ownerAccount;
	}

	public function getSourceCidrIp() {
		return $this->sourceCidrIp;
	}

	public function setSourceCidrIp($sourceCidrIp) {
		$this->sourceCidrIp = $sourceCidrIp;
		$this->queryParameters["SourceCidrIp"]=$sourceCidrIp;
	}

	public function getDestGroupId() {
		return $this->destGroupId;
	}

	public function setDestGroupId($destGroupId) {
		$this->destGroupId = $destGroupId;
		$this->queryParameters["DestGroupId"]=$destGroupId;
	}

	public function getOwnerId() {
		return $this->ownerId;
	}

	public function setOwnerId($ownerId) {
		$this->ownerId = $ownerId;
		$this->queryParameters["OwnerId"]=$ownerId;
	}

	public function getDestGroupOwnerAccount() {
		return $this->destGroupOwnerAccount;
	}

	public function setDestGroupOwnerAccount($destGroupOwnerAccount) {
		$this->destGroupOwnerAccount = $destGroupOwnerAccount;
		$this->queryParameters["DestGroupOwnerAccount"]=$destGroupOwnerAccount;
	}

	public function getPriority() {
		return $this->priority;
	}

	public function setPriority($priority) {
		$this->priority = $priority;
		$this->queryParameters["Priority"]=$priority;
	}

	public function getDestCidrIp() {
		return $this->destCidrIp;
	}

	public function setDestCidrIp($destCidrIp) {
		$this->destCidrIp = $destCidrIp;
		$this->queryParameters["DestCidrIp"]=$destCidrIp;
	}

	public function getDestGroupOwnerId() {
		return $this->destGroupOwnerId;
	}

	public function setDestGroupOwnerId($destGroupOwnerId) {
		$this->destGroupOwnerId = $destGroupOwnerId;
		$this->queryParameters["DestGroupOwnerId"]=$destGroupOwnerId;
	}
	
}