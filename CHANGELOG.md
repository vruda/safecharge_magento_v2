# Magento 2 Safecharge Payments Module

---

## 2.0.4.x
```
	* Removed admin option Save Order before success.
	* The DMN class no longer extends Action class.
	* In the DMN class added check for Declined Order - Auth or Sale type. In this case we stop process, and do not try to create Order.
	* In the DMN class added check for delay DMN, for example if the order was Settled and the DMN is for Auth transaction.
	* In the DMN class, when user does not reach Success Page, the time we wait before try to create an Order is changed to about 15 seconds.
	* In the DMN class, when the class try to create the Order we check if the Quote is active and if the used Payment Method is safecharge.
	* In the DMN class, for output messages, use JsonFactory, also set Response Code to 200.
	* On Succes Page we check if the Quote is active, we place Order only if it is. 