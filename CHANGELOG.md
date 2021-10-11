# OpenCart Nuvei Payments Module

---
### 2.2
```
	* In the settings "Test mode" was changed to "Sandbox Mode".
	* Added the missing third option for the "Enable UPOs" - "Please choose...".
```

### 2.1
```
	* When the DMN can not find the Order or code throw an exception, return status 400 to the DMN sender.
```

### 2.0
```
	* In Admin > Seles > Orders > View, History table, use full date.
	* For Auth, Sale and Settle transactions, save the Transaction ID in oc_order.custom_filed.
	* Revert order_status_id default value to 0.
	* Added page blocker after click on Pay button.
	* Implemented Update Order logic.
	* Fix a bug - not using Order statuses in the plugin settings;
	* Changed the structure of the saved Transaction data. Save all in the payment_custom_filed and do not use additional table;
	* Full rebranding.
```
