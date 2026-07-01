[Reflection.Assembly]::LoadWithPartialName("System.Drawing") | Out-Null
$img = [System.Drawing.Image]::FromFile("D:\Xampp\htdocs\restaurant_medusa\assets\images\versace_logo.png")
$img.Save("D:\Xampp\htdocs\restaurant_medusa\assets\images\versace_logo.jpg", [System.Drawing.Imaging.ImageFormat]::Jpeg)
$img.Dispose()
write-output "Logo successfully converted to JPEG."
