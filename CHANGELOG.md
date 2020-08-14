# Magento 2 Safecharge Payments Module

---

## 2.2.0.x
```
	* Added possibility to append Safecharge Payment Plan to a product, when create new or edit existing one. Plugin get the payment plans from the pre-created plans of the merchant, by click on a button Get Payment Plans in plugin configuration.
	* When user have a product with a Payment Plan, only CC is allowed.
	* Added third option for mandatory dorp-downs in the plugin settings.


## 2.1.0.x
```
	* Use 3-fields webSDK instead one-field version, because of mobile optimization;


## 2.0.5.x
```
	* In OpenOrder request pass order amount into custom fields, to get it again in response and pass it to the front-end.
	* A revert - DMN class again extends Action class.
	* In DMN class added check for the Order total and DMN total parameter, for Auth transactions. If they are not same, we stop process.
	* In DMN class, add userPaymentOptionId to the payment for future use.
	* In DMN class, when check for Partial Settle we compare Order Base Grand Total with DMN parameter totalAmount.
	* In DMN class, check for available request quote parameter in placeOrder method.
	* In safecharge.js, removed some unused classes.
	* In safecharge.js, observe for add/remove coupons, and compare the total with the total passed to the OpenOrder request. If there is difference, we call new OpenOrder with correct total amount.
	* Added validation for the emails first, they are restricted to 79 symbols;


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