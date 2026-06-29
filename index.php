<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        Inventory Management System
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-white text-gray-800">

    <!-- ================= NAVBAR ================= -->
    <nav class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">

            <!-- LOGO -->
            <div class="flex items-center gap-3">
                <div class="bg-blue-600 text-white w-12 h-12 rounded-xl flex items-center justify-center text-2xl">
                    📦
                </div>
                <div>
                    <h1 class="text-xl font-bold text-blue-600">
                        Smart Inventory
                    </h1>
                    <p class="text-sm text-gray-500">
                        Management System
                    </p>
                </div>
            </div>


            <!-- MENU -->
            <div class="hidden md:flex items-center gap-10">
                <a href="#home"
                    class="hover:text-blue-600">
                    Home
                </a>
                <a href="#features"
                    class="hover:text-blue-600">
                    Features
                </a>
                <a href="#about"
                    class="hover:text-blue-600">
                    About
                </a>
                <a href="#contact"
                    class="hover:text-blue-600">
                    Contact
                </a>
                <a href="auth/login.php"
                    class="bg-blue-600 text-white px-7 py-3 rounded-xl hover:bg-blue-700">
                    🔒 Login
                </a>
            </div>
        </div>
    </nav>


    <!-- ================= HERO SECTION ================= -->
    <section id="home" class="bg-gradient-to-r from-blue-50 to-white">
        <div class="max-w-7xl mx-auto px-8 py-20">
            <div class="grid md:grid-cols-2 gap-12 items-center">

                <!-- LEFT CONTENT -->
                <div>
                    <p class="font-semibold text-blue-600 uppercase">welcome to smart inventory</p>

                    <h1 class="text-6xl font-bold leading-tight mt-8">
                        Smart Inventory
                        <br>
                        <span class="text-blue-600">
                            Management System
                        </span>
                    </h1>
                    <p class="text-gray-600 text-xl mt-6 leading-relaxed">
                        Manage your products,stock, sales, and reports
                        easily and efficiently. All in one simple system
                        for your business growth.
                    </p>
                    <div class="flex gap-5 mt-10">
                        <a href="auth/login.php"
                            class="bg-blue-600 text-white px-8 py-4 rounded-xl font-semibold shadow-lg hover:bg-blue-700">
                            Get Started Now →
                        </a>
                        <a href="#features"
                            class="flex items-center gap-3 border border-blue-600 text-blue-600 px-8 py-4 rounded-xl">
                            <div class="w-8 h-8 rounded-full border flex items-center justify-center">
                                ▶
                            </div>
                            Learn More
                        </a>
                    </div>
                </div>
                <!-- RIGHT IMAGE AREA -->
                <div class="relative">
                    <div class="rounded-lg flex items-center justify-center mx-auto">
                        <img src="img/inventory1.png"
                            class="w-full"
                            alt="Inventory">
                    </div>
                </div>
            </div>
            <div class="flex justify-between mt-8  rounded-lg">
                <div class="flex items-center gap-4">
                    <img src="img/easy.png" alt="easy" class="w-8 h-8 rounded-lg border bg-green-100">
                    <div>
                        <h2>Easy to Use</h2>
                        <p>Simple and intuitive interface</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 ">
                    <img src="img/secure.png" alt="secure" class="w-8 h-8 rounded-lg border bg-green-100">
                    <div>
                        <h2>Secure & Reliable</h2>
                        <p>Your data is sale with us</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <img src="img/chart.png" alt="chart" class="w-8 h-8 rounded-lg border bg-green-100">
                    <div>
                        <h2>Real-time Reports</h2>
                        <p>Get instant insights anytime</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= FEATURES SECTION ================= -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-8">
            <!-- Title -->
            <div class="text-center mb-12">
                <p class="text-blue-600 font-bold tracking-wide">
                    OUR FEATURES
                </p>
                <h2 class="text-4xl font-bold mt-3">
                    Everything You Need to Manage Your Inventory
                </h2>
                <div class="w-16 h-1 bg-blue-600 mx-auto mt-5 rounded">
                </div>
            </div>

            <!-- Feature Cards -->
            <div class="grid md:grid-cols-6 gap-6">
                <!-- Product -->
                <div class="border rounded-2xl p-6 hover:shadow-lg transition flex flex-col text-center items-center">
                    <div class="bg-blue-100 w-14 h-14 rounded-xl flex  justify-center items-center text-3xl">
                        📦
                    </div>
                    <h3 class="font-bold text-xl mt-5">
                        Product Management
                    </h3>
                    <p class="text-gray-600 mt-3 leading-relaxed">
                        Add, edit and manage all
                        your products in one place.
                    </p>
                </div>

                <!-- Stock -->
                <div class="border rounded-2xl p-6 hover:shadow-lg transition flex flex-col text-center items-center">
                    <div class="bg-green-100 w-14 h-14 rounded-xl flex items-center justify-center text-3xl">
                        ⬇️
                    </div>
                    <h3 class="font-bold text-xl mt-5">
                        Stock Management
                    </h3>
                    <p class="text-gray-600 mt-3 leading-relaxed">
                        Track real-time stock levels and
                        get low stock alerts.
                    </p>
                </div>

                <!-- Sales -->
                <div class="border rounded-2xl p-6 hover:shadow-lg transition flex flex-col text-center items-center">
                    <div class="bg-orange-100 w-14 h-14 rounded-xl flex items-center justify-center text-3xl">
                        🛒
                    </div>
                    <h3 class="font-bold text-xl mt-5">
                        Sales Management
                    </h3>
                    <p class="text-gray-600 mt-3 leading-relaxed">
                        Create invoices, process sales and manage your customers.
                    </p>
                </div>

                <!-- Supplier -->
                <div class="border rounded-2xl p-6 hover:shadow-lg transition flex flex-col text-center items-center">
                    <div class="bg-purple-100 w-14 h-14 rounded-xl flex items-center justify-center text-3xl">
                        <img src="img/supplier.png" alt="supplier" class="w-10 h-10">
                    </div>
                    <h3 class="font-bold text-xl mt-5">
                        Supplier Management
                    </h3>
                    <p class="text-gray-600 mt-3 leading-relaxed">
                        Manage your suppliers and purchase records easily.
                    </p>
                </div>
                <!-- reports -->
                <div class="border rounded-2xl p-6 hover:shadow-lg transition flex flex-col text-center items-center">
                    <div class="bg-purple-100 w-14 h-14 rounded-xl flex items-center justify-center text-3xl">
                        <img src="img/supplier.png" alt="supplier" class="w-10 h-10">
                    </div>
                    <h3 class="font-bold text-xl mt-5">
                        Reprots & Analytics
                    </h3>
                    <p class="text-gray-600 mt-3 leading-relaxed">
                        Generate detailed reports and date-driven decisions.
                    </p>
                </div>
                <!-- User -->
                <div class="border rounded-2xl p-6 hover:shadow-lg transition flex flex-col text-center items-center">
                    <div class="bg-purple-100 w-14 h-14 rounded-xl flex items-center justify-center text-3xl">
                        <img src="img/supplier.png" alt="supplier" class="w-10 h-10">
                    </div>
                    <h3 class="font-bold text-xl mt-5">
                        User Management
                    </h3>
                    <p class="text-gray-600 mt-3 leading-relaxed">
                        Manage users and roles with different access permissions.
                    </p>
                </div>
            </div>
        </div>

        </div>

        </div>
    </section>
    <!-- Hero Section -->


    <section class="bg-blue-50 py-20">


        <div class="max-w-6xl mx-auto px-6 grid md:grid-cols-2 gap-10 items-center">


            <div>


                <p class="text-blue-600 font-semibold">

                    ABOUT US

                </p>


                <h2 class="text-5xl font-bold mt-4 leading-tight">


                    Simplify Your

                    <br>

                    Inventory Management


                </h2>



                <p class="mt-6 text-gray-600 text-lg">


                    Smart Inventory Management System is a complete
                    solution designed to help businesses manage
                    products, suppliers, stock and sales efficiently.


                </p>


                <a href="features.php"

                    class="inline-block mt-8 bg-blue-600 text-white px-8 py-3 rounded-lg">

                    Explore Features

                </a>


            </div>





            <div class="flex justify-center">


                <div class="bg-white rounded-3xl shadow-xl p-12">


                    <div class="text-8xl">

                        📦

                    </div>


                </div>


            </div>


        </div>


    </section>








    <!-- Introduction -->


    <section class="py-20">


        <div class="max-w-5xl mx-auto px-6 text-center">


            <h2 class="text-4xl font-bold">

                Who We Are

            </h2>


            <p class="mt-6 text-gray-600 text-lg leading-relaxed">


                We provide a simple and powerful inventory
                management solution that helps small and
                medium businesses control their products,
                purchases, sales and reports in one system.


            </p>


        </div>


    </section>









    <!-- Mission Vision Goal -->


    <section class="bg-gray-50 py-20">


        <div class="max-w-6xl mx-auto px-6">


            <h2 class="text-4xl font-bold text-center">

                Our Purpose

            </h2>



            <div class="grid md:grid-cols-3 gap-8 mt-12">



                <div class="bg-white p-8 rounded-xl shadow">


                    <div class="text-4xl">

                        🎯

                    </div>


                    <h3 class="text-xl font-bold mt-5">

                        Our Mission

                    </h3>


                    <p class="text-gray-600 mt-3">

                        To provide an easy and efficient
                        system for managing inventory
                        and business operations.

                    </p>


                </div>






                <div class="bg-white p-8 rounded-xl shadow">


                    <div class="text-4xl">

                        👁️

                    </div>


                    <h3 class="text-xl font-bold mt-5">

                        Our Vision

                    </h3>


                    <p class="text-gray-600 mt-3">

                        To become a reliable inventory
                        solution for modern businesses.

                    </p>


                </div>






                <div class="bg-white p-8 rounded-xl shadow">


                    <div class="text-4xl">

                        🚀

                    </div>


                    <h3 class="text-xl font-bold mt-5">

                        Our Goal

                    </h3>


                    <p class="text-gray-600 mt-3">

                        Help businesses save time,
                        reduce errors and increase
                        productivity.

                    </p>


                </div>



            </div>


        </div>


    </section>









    <!-- Why Choose Us -->


    <section class="py-20">


        <div class="max-w-6xl mx-auto px-6">


            <div class="grid md:grid-cols-2 gap-10 items-center">


                <div>


                    <h2 class="text-4xl font-bold">

                        Why Choose SmartInventory?

                    </h2>


                    <p class="text-gray-600 mt-5">


                        Our system provides everything needed
                        to manage daily inventory operations
                        quickly and accurately.


                    </p>


                    <ul class="mt-6 space-y-4">


                        <li>
                            ✓ Easy Product Management
                        </li>


                        <li>
                            ✓ Real-time Stock Tracking
                        </li>


                        <li>
                            ✓ Supplier Management
                        </li>


                        <li>
                            ✓ Sales & Payment Management
                        </li>


                        <li>
                            ✓ Detailed Reports
                        </li>


                    </ul>


                </div>






                <div class="bg-blue-600 text-white p-10 rounded-3xl">


                    <h3 class="text-3xl font-bold">
                        Smart Business,
                        Smart Inventory

                    </h3>


                    <p class="mt-5 text-blue-100">

                        Manage your entire business
                        from one simple platform.

                    </p>


                </div>



            </div>


        </div>


    </section>








    <!-- Statistics -->


    <section class="bg-blue-600 py-16 text-white">


        <div class="max-w-6xl mx-auto px-6 grid md:grid-cols-4 gap-8 text-center">


            <div>

                <h2 class="text-4xl font-bold">
                    100+
                </h2>

                <p>
                    Products Managed
                </p>

            </div>



            <div>

                <h2 class="text-4xl font-bold">
                    50+
                </h2>

                <p>
                    Suppliers
                </p>

            </div>



            <div>

                <h2 class="text-4xl font-bold">
                    1000+
                </h2>

                <p>
                    Sales Records
                </p>

            </div>



            <div>

                <h2 class="text-4xl font-bold">
                    24/7
                </h2>

                <p>
                    Support
                </p>

            </div>



        </div>


    </section>


    <!--  Contact -->
    <section id="contact" class="bg-[#E8ECFF] py-20">
        <div class="max-w-6xl mx-auto px-6 grid md:grid-cols-2 gap-10 items-center">
            <div>
                <p class="text-blue-600 font-semibold">
                    CONTACT US
                </p>
                <h2 class="text-5xl font-bold mt-4 leading-tight">
                    We'd Love to
                    <br>
                    Hear From You
                </h2>
                <p class="text-gray-600 mt-6 text-lg">
                    Have questions or need support?
                    Feel free to reach out to us.
                    We are here to help!
                </p>
            </div>
            <div class="flex justify-center">
                <img src="img/message.png" alt="Message">
            </div>
        </div>
    </section>
    <!-- Contact Cards -->
    <section class="py-16">
        <div class="max-w-6xl mx-auto px-6">
            <div class="grid md:grid-cols-4 gap-6">
                <!-- Phone -->
                <div class="bg-white shadow rounded-xl p-6">
                    <div class="text-blue-600 text-3xl">
                        ☎
                    </div>
                    <h3 class="font-bold text-xl mt-4">
                        Phone
                    </h3>
                    <p class="text-gray-500 mt-2">
                        +95 9 123 456 789
                    </p>
                </div>

                <!-- Email -->

                <div class="bg-white shadow rounded-xl p-6">


                    <div class="text-green-500 text-3xl">
                        ✉
                    </div>


                    <h3 class="font-bold text-xl mt-4">

                        Email

                    </h3>


                    <p class="text-gray-500 mt-2">

                        info@smartinventory.com

                    </p>


                </div>

                <!-- Address -->

                <div class="bg-white shadow rounded-xl p-6">


                    <div class="text-purple-600 text-3xl">

                        📍

                    </div>


                    <h3 class="font-bold text-xl mt-4">

                        Address

                    </h3>


                    <p class="text-gray-500 mt-2">

                        No.123, Main Street,
                        Yangon, Myanmar

                    </p>


                </div>


                <!-- Time -->

                <div class="bg-white shadow rounded-xl p-6">


                    <div class="text-orange-500 text-3xl">

                        ◷

                    </div>


                    <h3 class="font-bold text-xl mt-4">

                        Working Hours

                    </h3>


                    <p class="text-gray-500 mt-2">

                        Mon - Fri:
                        <br>

                        9:00 AM - 6:00 PM

                    </p>


                </div>




            </div>


        </div>


    </section>

    <!-- CTA -->


    <section class="py-10">


        <div class="max-w-6xl mx-auto px-6">


            <div class="bg-blue-50 rounded-2xl p-10 flex md:flex-row flex-col justify-between items-center">


                <div>


                    <h2 class="text-3xl font-bold">

                        Let's Work Together

                    </h2>


                    <p class="text-gray-600 mt-3">

                        We are always open to discussing
                        new projects and ideas.

                    </p>


                </div>



                <a href="mailto:info@smartinventory.com"

                    class="mt-5 md:mt-0 bg-blue-600 text-white px-8 py-3 rounded-lg">


                    Get In Touch →

                </a>



            </div>


        </div>


    </section>


    <!-- Footer -->


    <footer class="bg-gray-900 text-white mt-10">


        <div class="max-w-7xl mx-auto px-6 py-12 grid md:grid-cols-4 gap-8">



            <div>

                <h2 class="text-2xl font-bold text-blue-400">

                    SmartInventory

                </h2>


                <p class="text-gray-400 mt-3">

                    Complete inventory management
                    solution for your business.

                </p>

            </div>





            <div>

                <h3 class="font-bold mb-4">

                    Quick Links

                </h3>


                <p>Home</p>

                <p>About</p>

                <p>Features</p>

                <p>Pricing</p>

                <p>Contact</p>


            </div>






            <div>

                <h3 class="font-bold mb-4">

                    Support

                </h3>


                <p>Help Center</p>

                <p>Terms of Service</p>

                <p>Privacy Policy</p>

                <p>FAQ</p>


            </div>






            <div>

                <h3 class="font-bold mb-4">

                    Contact Info

                </h3>


                <p>
                    ☎ +95 9 123 456 789
                </p>


                <p>
                    ✉ info@smartinventory.com
                </p>


                <p>
                    📍 Yangon, Myanmar
                </p>


            </div>


        </div>
        <div class="border-t border-gray-700 text-center py-5 text-gray-400">


            © 2026 Smart Inventory Management System.
            All rights reserved.


        </div>


    </footer>



</body>

</html>