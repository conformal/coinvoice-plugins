/**
 * @author Conformal Systems LLC.
 * @copyright Copyright (c) 2014 Conformal Systems LLC. <support@conformal.com>
 * @license
 * Copyright (c) Conformal Systems LLC. <support@conformal.com>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */
(function($) {
	$(document).ready(function() {
		// clock function to display countdown until rate expiration
		var clock = setInterval(function(){
		// set whatever future date / time you want here, together with
			// your timezone setting...
			if ($("#endTime").text() != "") {
				var future = new Date($("#endTime").text());
				var now = new Date();
				var difference = Math.floor((future - now) / 1000);
				var seconds = fixIntegers(difference % 60);
				difference = Math.floor(difference / 60);
				var minutes = fixIntegers(difference % 60);
				difference = Math.floor(difference / 60);
				if (seconds == 0 && minutes == 0) {
					timerComplete();
					clearInterval(clock);
					clearInterval(check);
				}
				$("#timeLeft").text(minutes + ":" + seconds);
			} else {
				clearInterval(clock);
				clearInterval(check);
			}
		}, 1000);
		function timerComplete() {
			var btcHide = document.getElementById("btcPaymentInfo");
			btcHide.style.display="none";
			var timerUp = document.getElementById("timerUp");
			timerUp.style.display="block";
		}
		function fixIntegers(integer){
			if (integer < 0)
			        integer = 0;
		    	if (integer < 10)
		        	return "0" + integer;
	    		return "" + integer;
		}
		var txFound = false;
		var foundError = true;
		// check to see if browser is able to create websockets
		if( typeof(WebSocket) == "function" ) {
			var websocket;
			if (location.protocol == "http:") {
				websocket = "ws://";
			} else if (location.protocol == "https:") {
				websocket = "wss://";
			}
			// Create a socket
			var socket = new WebSocket(websocket+window.location.host+'/paymentportal/check?code='+$("#paymentCode").text());
			// Message received on the socket
			socket.onmessage = function(event) {
				data = JSON.parse(event.data);
				if (data.Type == "message") {
					// transaction found message
					if (data.Paid == "txfound") {
						paid = data.Paid;
						var amt = data.AmntReceived;
						var date = new Date(data.Timestamp*1000);
						var minConf = data.MinConf;
						if (!txFound) {
							$("#txFoundAmt").append($("<td>", {
								text:"BTC " + amt
							}));
							$("#txFoundTime").append($("<td>", {
								text:date
							}));
							$("#btcPaymentInfo").hide(1);
							$("#txFound").show(1);
							txFound = true;
						}
						// update confirmations
						clearInterval(clock);
						displayConfirmations(minConf);
					// fully confirmed/paid message
					} else if (data.Paid == "paid") {
						paid = data.Paid;
						amt = data.AmntReceived;
						var date = new Date(data.Timestamp*1000);
						$("#btcPaid").append($("<td>", {
							text:"BTC " + amt
						}));
						$("#btcPaidTime").append($("<td>", {
							text:date
						}));
						$("#btcPaymentInfo").hide(1);
						$("#txFound").hide(1);
						$("#paid").show(1);
						foundError = false;
						socket.close();
					// partial payment message
					} else if (data.Paid == "partial") {
						$("#qrcode").empty();
						var previousAmount = $("#toBePaid").text();
						var newAmount = parseFloat(previousAmount) - parseFloat(data.AmntReceived);
						$("#toBePaid").empty();
						$("#toBePaid").text(newAmount);
						// update qrcode and amounts to pay
						var qrcode = new QRCode(document.getElementById("qrcode"), {
							width : 150,
							height : 150
						});
						var vcAddress = $("#vcAddress").text()
						var amtToPay = $("#toBePaid").text()
						qrcode.makeCode("bitcoin:"+vcAddress+"?amount="+amtToPay+"&label=Coinvoice-Invoice");
						if (newAmount != 0) {
							$("#partialPayment").remove();
							$("#partialPaymentInfo").append($("<p>", {
								class:"text-center",
								id:"partialPayment",
								text:"Partial Payment "+  data.AmntReceived
							}));
						}
					// not paid message/default message
					} else if (data.Paid == "not paid") {
						var date = new Date(data.Timestamp*1000)
					}
				// errors received from the websocket
				} else if (data.Type == "error") {
					$("#btcPaymentInfo").hide(1);
					$("#txFound").hide(1);
					$("#errorMessageArea").append($("<span>", {
						class:"text-center error",
						id:"errorFromServer",
						text:data.ErrorMessage
					}));
					$("#errorPayment").show(1);
					socket.close();
				}
			}
			socket.onclose = function(event) {
				// if websocket closes unexpectedly, check for foundError.
				if (foundError) {
					$("#btcPaymentInfo").hide(1);
					$("#txFound").hide(1);
					$("#errorMessageArea").append($("<span>", {
						class:"text-center error",
						id:"errorFromServer",
						text:"Our serve has unexpectedly disconnected.  Please reload this page."
					}));
					$("#errorPayment").show(1);
					clearInterval(clock);
				}
			}
		} else {
			// if browser does not handle websockets, use long poll post (default set to 30s)
			var check = setInterval(function(){
				if ($("#endTime").text() != "") {
					$.ajax({type: 'POST',
						url: '/paymentportal/check/ajax/',
						data: {code: $("#paymentCode").text()},
						success:function(data) {
							data = data.trim();
							split_response = data.split("_");
							if (split_response.length == 3) {
								paid = split_response[0];
								amt = split_response[1];
								time_paid = new Date(split_response[2]);
								if (paid == "paid") {
									$("#btcPaymentInfo").hide(1);
									$("#paidMessageArea").append($("<td>", {
										class:"text-center",
										id:"paidAmt",
										text:amt
									}));
									$("#paidMessageArea").append($("<td>", {
										class:"text-center",
										id:"paidTime",
										text:time_paid
									}));
									$("#paid").show(1);
									clearInterval(check);
									clearInterval(clock);
								}
							} else if (split_response.length == 4) {
								paid = split_response[0];
								amt = split_response[1];
								minConf = spli_response[2];
								date = new Date(split_response[3]);
								if (paid == "txfound") {
									if (!txFound) {
										$("#txFoundAmt").append($("<td>", {
											text:"BTC " + amt
										}));
										$("#txFoundTime").append($("<td>", {
											text:date
										}));
										$("#btcPaymentInfo").hide(1);
										$("#txFound").show(1);
										txFound = true;
									}
									// update confirmations
									clearInterval(clock);
									displayConfirmations(minConf);
								}
							}else if (split_response.length == 2) {
								if (split_response[0] == "error") {
									$("#btcPaymentInfo").hide(1);
									$("#errorMessageArea").append($("<span>", {
										class:"text-center error",
										id:"errorFromServer",
										text:split_response[1]
									}));
									$("#errorPayment").show(1);
									clearInterval(check);
									clearInterval(clock);
								} else if (split_response[0] == "partial") {
									$("#qrcode").empty();
									$("#toBePaid").empty();
									$("#toBePaid").text(split_response[1]);
									var qrcode = new QRCode(document.getElementById("qrcode"), {
										width : 150,
										height : 150
									});
									var vcAddress = $("#vcAddress").text()
									var amtToPay = $("#toBePaid").text()
									qrcode.makeCode("bitcoin:"+vcAddress+"?amount="+amtToPay+"&label=Coinvoice-Invoice");
									$("#partialPayment").remove();
									$("#partialPaymentInfo").append($("<p>", {
										id:"partialPayment",
										text:"Partial Payment "+ split_response[1]
									}));
								}
							}
						}
   					});
				} else {
					clearInterval(check);
				}
			},30000);
		}
		// render qrcode
		var qrcode = new QRCode(document.getElementById("qrcode"), {
			width:130,
			height:130
		});
		var vcAddress = $("#vcAddress").text();
		var amtToPay = $("#toBePaid").text();
		qrcode.makeCode("bitcoin:"+vcAddress+"?amount="+amtToPay+"&label=Coinvoice-Invoice");
		function displayConfirmations(minConf) {
			for (var i=1;i<=minConf;i++){
				$("#confirm"+i).addClass("confirmed");
				$('#confirm'+i).removeClass('running').delay(10).queue(function(next){
					$(this).addClass('running');
				        next();	
				});
			}
		}
	});
})(jQuery);
