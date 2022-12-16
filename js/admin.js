// Source: https://stackoverflow.com/a/27747377
// dec2hex :: Integer -> String
// i.e. 0-255 -> '00'-'ff'
function dec2hex(dec) {
	return dec < 10
		? '0' + String(dec)
		: dec.toString(16);
}

// generateId :: Integer -> String
function generateNonce(len) {
	var arr = new Uint8Array((len || 40) / 2);
	window.crypto.getRandomValues(arr);
	return Array.from(arr, dec2hex).join('');
}

var nonce = false;

function clickSubmit() {
	nonce = generateNonce();

	var client_id = document.getElementById("client-id").value;
	var sign_in_sso = document.getElementById("sso-sign-in");
	if (client_id) {
		var url = "https://login.salesforce.com/services/oauth2/authorize?client_id=" + client_id + "&redirect_uri=" +
			window.location.href.split('?')[0] + '?page=pardot' + "&response_type=code" + "&display=popup" + "&scope=refresh_token%20pardot_api" +
			"&state=" + nonce + "&code_challenge=" + Pardot.challenge;
		window.open(url, "Sign In with Salesforce", "height=800, width=400, left=" + sign_in_sso.getBoundingClientRect().right);
	}
	else {
		alert("Please type in a valid Consumer Key.");
	}
}

window.loginCallback = function (urlString) {
	var url = new URL(urlString);
	var returnedState = url.searchParams.get('state');
	if (returnedState === nonce) {
		url.searchParams.append('status', 'success');
		window.location.replace(url);
	}
	else {
		alert("Invalid state parameter returned.");
	}
};

const urlParams = new URLSearchParams(window.location.search);
const codeParam = urlParams.get('code');
const statusParam = urlParams.get('status');

if (codeParam && codeParam.length > 1 && !statusParam) {

	window.opener.loginCallback(window.location.href);
	window.close();
}
