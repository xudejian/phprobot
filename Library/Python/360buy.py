from PIL import Image

from captchaidentifier_360buy import CaptchaIdentifier

import sys,cookielib,StringIO, urllib, urllib2

def getTextPriceFromImage(url):
    identify = CaptchaIdentifier()
    CAPTHA=url
    cookie = cookielib.CookieJar()
    opener = urllib2.build_opener(urllib2.HTTPCookieProcessor(cookie))
    img_file = opener.open(CAPTHA)
    tmp = StringIO.StringIO(img_file.read())
    image = Image.open(tmp)
    return identify.parse(image)

if __name__ == '__main__':
    url = sys.argv[1]
    if url:
        priceText = getTextPriceFromImage(url)
        print(priceText)
