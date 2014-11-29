OpenStack-PHP
=============

An OpenStack library for PHP 5.4+

Currently, only Identity and Object Storage is implemented. I plan on implementing Compute next.

Adding this library into your application is as simple as
```php
require_once("OpenStack.php");
```
The lack of autoloader support is intended to make it easy for command line users.
Calls that require communication with OpenStack will allow additional headers to be sent as the last argument. (e.g login($headers = array()))
 
All errors may be retrieved with the same format regardless of object.
```php
print_r($PHPObject->getErrors());
```

Successful actions return either true or the affected object/container/account/etc., failed actions return "false".
##Table of Contents
- [Identity](#identity)
  + [Logging In](#logging-in)
  + [Reading Service Catalog](#reading-service-catalog)
  + [Reading Access Token](#reading-access-token)
- [Object Storage](#object-storage)
  + [Getting Details](#getting-details)
    + [Update Metadata](#update-object-storage-metadata)
  + [Containers](#containers)
    + [Getting Details](#getting-container-details)
      + [Updating Metadata](#updating-container-metadata)
    + [Creating Container](#creating-container)
    + [Deleting Container](#deleting-container)
  + [Objects](#objects)
    + [Getting Details](#getting-object-details)
      + [Updating Metadata](#updating-object-metadata)
    + [Creating Objects](#creating-objects)
    + [Getting Data](#getting-data)
    + [Copying Object](#copying-object)
    + [Deleting Object](#deleting-object)

##Identity

###Logging in
We'll need to create an OSIdentity (OpenStack Identity) Object to handle our credentials. We can go ahead and initialize our OSIdentity with an authentication endpoint, username, and password.

Additional options such as tenantId may be specified following initialization.
```php
$Identity = new OSIdentity("https://auth.net/", "username123", "password456");
// $Identity->tenantId = "987654321";
```

You may login with the login() method.
```php
$Identity->login();
```
### Reading Service Catalog
Services, their respective metadata, and endpoints will be stored in the $Identity->serviceCatalog object (of type OSServiceCatalog.) You can get certain services by either name or type.
```php
print_r($Identity->serviceCatalog->getServicesByName("swift"));
print_r($Identity->serviceCatalog->getServicesByType("object-store"));
print_r($Identity->serviceCatalog->getServices());
```
You can get specific endpoints by region

```php
print_r($Identity->serviceCatalog->getServicesByName("swift")[0]->getEndpointsByRegion("BHS-1");
print_r($Identity->serviceCatalog->getServicesByName("swift")[0]->getEndpoints());
```
### Reading Access Token
The access token is located at $Identity->accessToken. It contains issue date, expiry date, ID, and tenant information. You may get the timestamp in 'America/Los_Angeles' time of expiry with the accessToken->getDeathTimestamp() method.

```php
print_r($Identity->accessToken);
print_r($Identity->accessToken->getDeathTimestamp());
```
##Object Storage
We must initialize our ObjectStore object with our Identity object. The Identity attribute of our ObjectStore is public, so individual settings are open to  tampering. By default, our ObjectStore will use the first service with the "object-store" type, and its first endpoint.

```php
$ObjectStore = new OSObjectStore($Identity);
//$ObjectStore->objectStoreURL = "http://identity.net/endpoint";
```
###Getting Details
There are a few different ways to get details about our Object Storage account. 
```php
print_r($ObjectStore->getDetails()); //Metadata and container info
print_r($ObjectStore->getMetadata()); //Purely metadata
print_r($ObjectStore->getContainers()); //Get Container objects
print_r($ObjectStore->getContainerList()); //Get Containers in neat list
print_r($ObjectStore->getContainerByName("CDN")); //Get a single Container object
```
####Updating Object Storage Metadata
Metadata and their respective values will be passed along as headers in compliance with [OpenStack's documentation](http://developer.openstack.org/api-ref-objectstorage-v1.html).
```php
$ObjectStore->updateMetadata(array("X-Account-Meta-Author: John"));
$ObjectStore->updateMetadata(array("X-Remove-Account-Meta-Author: John"));
```
###Containers
Containers are independent objects, and inherently store all the information they need.
```php
$Container = $ObjectStore->getContainerByName("testing");
```
####Creating Container
Containers may be created with a single function on the ObjectStore level.
```php
$Container = $ObjectStore->createContainer("test");
```
####Deleting Container
Deleting may be done with a single function.
```php
$Container->delete();
```
####Getting Details
There are multiple functions that will let you retrieve details about your container.
```php
print_r($Container->getDetails()); //Get headers and object list
print_r($Container->getMetadata()); //Get metadata
print_r($Container->getObjects()); //Get proper objects
print_r($Container->getObjectList()); //Get Object List
print_r($Container->getObjectByName("testing")); //Get a single object
```
#####Updating Container Metadata
Metadata and their respective values will be passed along as headers in compliance with [OpenStack's documentation](http://developer.openstack.org/api-ref-objectstorage-v1.html).
```php
$Container->updateMetadata(array("X-Container-Meta-Author: John"));
$Container->updateMetadata(array("X-Remove-Container-Meta-Author: John"));
```
###Objects
Objects are independent (PHP)objects, and inherently store all the information they need.
```php
$Object = $Container->getObjectByName("testing");
```
####Getting Object Details
There are a couple ways to get object details. Keep in mind, *getDetails gets both metadata information and data*.
```php
print_r($Object->getDetails()); //Gets object details and data
print_r($Object->getMetadata()); //Just get object metadata
```
#####Updating Object Metadata
Metadata and their respective values will be passed along as headers in compliance with [OpenStack's documentation](http://developer.openstack.org/api-ref-objectstorage-v1.html).
```php
$Object->updateMetadata(array("X-Object-Meta-Author: John"));
$Object->updateMetadata(array("X-Remove-Object-Meta-Author: John"));
```
####Creating Objects
Creating objects may be simply done with a single function. If an object with the same name already exists, it is overwritten.
```php
$Container->createObject("name", "lots of data here");
```
####Getting Data
Data may be accessed by using the object as a string, or with the getData() function.
```php
echo $Object->getData();
echo $Object;
```
####Copying Object
Objects may be copied with a single function. The new object is returned if the function succeeds.
```php
$copyOfObject = $Object->copy("new-container/new-destination");
```
####Deleting Object
Objects may be deleted with the following function.
```php
$Object->delete();
```
